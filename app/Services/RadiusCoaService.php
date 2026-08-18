<?php

namespace App\Services;

use App\Models\CustomerService;
use App\Models\NetworkNas;
use App\Models\Radacct;
use App\Models\RadiusActionLog;
use RuntimeException;
use Throwable;

class RadiusCoaService
{
    public function __construct(private readonly RadiusPacketClient $client) {}

    /** @return array<string,mixed> */
    public function disconnectSession(Radacct $session, ?int $actorUserId = null): array
    {
        $this->assertOnlineSession($session);
        [$service, $nas] = $this->context($session);
        $lines = $this->sessionIdentityLines($session);

        return $this->runAndLog('disconnect', $session, $service, $nas, $lines, $actorUserId);
    }

    /** @return array<string,mixed> */
    public function applyPlanToSession(Radacct $session, ?int $actorUserId = null): array
    {
        $this->assertOnlineSession($session);
        [$service, $nas] = $this->context($session);
        if ($service->status !== 'active') {
            throw new RuntimeException('CoA plan hanya boleh dikirim untuk layanan aktif.');
        }
        $service->loadMissing('plan');
        if (! $service->plan) {
            throw new RuntimeException('Layanan tidak memiliki Internet Plan.');
        }

        $rateLimit = (string) (($service->plan->radius_attributes ?? [])['Mikrotik-Rate-Limit'] ?? '');
        if ($rateLimit === '') {
            $rateLimit = sprintf('%dk/%dk', $service->plan->upload_kbps, $service->plan->download_kbps);
        }

        $lines = [
            ...$this->sessionIdentityLines($session),
            'Mikrotik-Rate-Limit = '.$this->quoted($rateLimit),
        ];

        return $this->runAndLog('coa', $session, $service, $nas, $lines, $actorUserId);
    }

    /** @return array{attempted:int,succeeded:int,failed:int,errors:array<int,string>} */
    public function disconnectAllForService(CustomerService $service, ?int $actorUserId = null): array
    {
        return $this->disconnectAllForUsername($service, $service->pppoe_username, $actorUserId);
    }

    /** @return array{attempted:int,succeeded:int,failed:int,errors:array<int,string>} */
    public function disconnectAllForUsername(CustomerService $service, string $username, ?int $actorUserId = null): array
    {
        return $this->forEachOnlineSessionByUsername($username, function (Radacct $session) use ($service, $actorUserId) {
            $nas = $this->resolveNas($session, $service);
            return $this->runAndLog('disconnect', $session, $service, $nas, $this->sessionIdentityLines($session), $actorUserId);
        });
    }

    /** @return array{attempted:int,succeeded:int,failed:int,errors:array<int,string>} */
    public function applyPlanToAllSessions(CustomerService $service, ?int $actorUserId = null): array
    {
        return $this->forEachOnlineSession($service, fn (Radacct $session) => $this->applyPlanToSession($session, $actorUserId));
    }

    /**
     * @param callable(Radacct):array<string,mixed> $callback
     * @return array{attempted:int,succeeded:int,failed:int,errors:array<int,string>}
     */
    private function forEachOnlineSession(CustomerService $service, callable $callback): array
    {
        return $this->forEachOnlineSessionByUsername($service->pppoe_username, $callback);
    }

    /**
     * @param callable(Radacct):array<string,mixed> $callback
     * @return array{attempted:int,succeeded:int,failed:int,errors:array<int,string>}
     */
    private function forEachOnlineSessionByUsername(string $username, callable $callback): array
    {
        $sessions = Radacct::query()->online()->where('username', $username)->get();
        $result = ['attempted' => 0, 'succeeded' => 0, 'failed' => 0, 'errors' => []];

        foreach ($sessions as $session) {
            $result['attempted']++;
            try {
                $response = $callback($session);
                if ($response['success'] ?? false) {
                    $result['succeeded']++;
                } else {
                    $result['failed']++;
                    $result['errors'][] = (string) ($response['output'] ?? 'RADIUS action gagal.');
                }
            } catch (Throwable $e) {
                $result['failed']++;
                $result['errors'][] = $e->getMessage();
            }
        }

        return $result;
    }

    private function assertOnlineSession(Radacct $session): void
    {
        if ($session->acctstoptime !== null) {
            throw new RuntimeException('Session RADIUS sudah closed; CoA/Disconnect dibatalkan.');
        }
    }

    /** @return array{0:CustomerService,1:NetworkNas} */
    private function context(Radacct $session): array
    {
        $service = CustomerService::withTrashed()
            ->with(['plan', 'nas'])
            ->where('pppoe_username', $session->username)
            ->first();

        if (! $service) {
            throw new RuntimeException('Session tidak terhubung ke layanan tenant aktif.');
        }

        return [$service, $this->resolveNas($session, $service)];
    }

    private function resolveNas(Radacct $session, CustomerService $service): NetworkNas
    {
        // Prefer the NAS that actually originated this accounting session.  This
        // is important when a service was moved to a different router while an
        // old PPPoE session is still alive on the previous NAS.
        $nas = null;
        if ($session->nasipaddress) {
            $nas = NetworkNas::query()->where('nasname', $session->nasipaddress)->first();
        }
        $nas ??= $service->nas;

        if (! $nas) {
            throw new RuntimeException('NAS belum dipetakan ke layanan/session ini. Atur NAS pada layanan pelanggan terlebih dahulu.');
        }
        if (! $nas->active) {
            throw new RuntimeException('NAS untuk session ini sedang nonaktif.');
        }

        return $nas;
    }

    /** @return array<int,string> */
    private function sessionIdentityLines(Radacct $session): array
    {
        $lines = ['User-Name = '.$this->quoted((string) $session->username)];
        if ($session->acctsessionid) {
            $lines[] = 'Acct-Session-Id = '.$this->quoted((string) $session->acctsessionid);
        }
        if ($session->nasipaddress) {
            $lines[] = 'NAS-IP-Address = '.(string) $session->nasipaddress;
        }
        if ($session->framedipaddress) {
            $lines[] = 'Framed-IP-Address = '.(string) $session->framedipaddress;
        }

        return $lines;
    }

    /** @param array<int,string> $lines @return array<string,mixed> */
    private function runAndLog(
        string $action,
        Radacct $session,
        CustomerService $service,
        NetworkNas $nas,
        array $lines,
        ?int $actorUserId,
    ): array {
        try {
            $packet = $this->client->sendLines(
                (string) $nas->nasname,
                (int) $nas->coa_port,
                $action === 'disconnect' ? 'disconnect' : 'coa',
                (string) $nas->secret,
                $lines,
            );
            $expected = $action === 'disconnect' ? 'Disconnect-ACK' : 'CoA-ACK';
            $success = $packet['response_code'] === $expected;

            RadiusActionLog::create([
                'tenant_id' => $service->tenant_id,
                'customer_service_id' => $service->id,
                'radacctid' => $session->getKey(),
                'network_nas_id' => $nas->id,
                'action' => $action,
                'target' => $packet['target'],
                'request_payload' => ['lines' => $lines],
                'response_code' => $packet['response_code'],
                'success' => $success,
                'output' => mb_substr((string) $packet['output'], 0, 16000),
                'actor_user_id' => $actorUserId,
            ]);

            if ($success) {
                if ($action === 'disconnect') {
                    $service->forceFill(['last_disconnect_at' => now()])->saveQuietly();
                } else {
                    $service->forceFill(['last_coa_at' => now()])->saveQuietly();
                }
            }

            return [
                ...$packet,
                'success' => $success,
                'service_id' => $service->id,
                'nas_id' => $nas->id,
            ];
        } catch (Throwable $e) {
            RadiusActionLog::create([
                'tenant_id' => $service->tenant_id,
                'customer_service_id' => $service->id,
                'radacctid' => $session->getKey(),
                'network_nas_id' => $nas->id,
                'action' => $action,
                'target' => sprintf('%s:%d', $nas->nasname, $nas->coa_port),
                'request_payload' => ['lines' => $lines],
                'response_code' => null,
                'success' => false,
                'output' => mb_substr($e->getMessage(), 0, 16000),
                'actor_user_id' => $actorUserId,
            ]);
            throw $e;
        }
    }

    private function quoted(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }
}
