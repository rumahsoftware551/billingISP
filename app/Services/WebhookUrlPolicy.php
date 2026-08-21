<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class WebhookUrlPolicy
{
    public function __construct(private NetworkAddressPolicy $addresses) {}

    public function validateOrFail(string $url): void
    {
        $this->resolveForDelivery($url);
    }

    /** @return array{allow_redirects:false,curl?:array<int,array<int,string>>} */
    public function httpOptions(string $url): array
    {
        $target = $this->resolveForDelivery($url);
        $options = ['allow_redirects' => false];

        if (! filter_var($target['host'], FILTER_VALIDATE_IP)) {
            if (! defined('CURLOPT_RESOLVE')) {
                throw new RuntimeException('PHP cURL extension wajib tersedia untuk DNS pinning webhook.');
            }
            $entries = [];
            foreach ($target['addresses'] as $address) {
                $pinned = str_contains($address, ':') ? '['.$address.']' : $address;
                $entries[] = $target['host'].':'.$target['port'].':'.$pinned;
            }
            $options['curl'] = [CURLOPT_RESOLVE => $entries];
        }

        return $options;
    }

    /** @return array{host:string,port:int,addresses:list<string>} */
    private function resolveForDelivery(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw ValidationException::withMessages(['url' => 'Webhook URL harus berupa HTTP/HTTPS URL yang valid.']);
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw ValidationException::withMessages(['url' => 'Webhook URL tidak boleh mengandung username/password.']);
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if ($port < 1 || $port > 65535) {
            throw ValidationException::withMessages(['url' => 'Port webhook tidak valid.']);
        }

        if (app()->environment('production') && $scheme !== 'https' && ! (bool) config('jaringanku.webhook_allow_insecure_http', false)) {
            throw ValidationException::withMessages(['url' => 'Production webhook harus menggunakan HTTPS.']);
        }

        if (in_array($host, ['localhost', 'localhost.localdomain'], true) || str_ends_with($host, '.localhost')) {
            throw ValidationException::withMessages(['url' => 'Webhook ke localhost/private network diblokir.']);
        }

        try {
            $addresses = $this->addresses->resolveAll($host);
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['url' => $e->getMessage()]);
        }

        if (! app()->environment('local') && ! (bool) config('jaringanku.webhook_allow_private_networks', false)) {
            foreach ($addresses as $address) {
                if (! $this->addresses->isPublic($address)) {
                    throw ValidationException::withMessages(['url' => 'Webhook ke private/reserved IP diblokir.']);
                }
            }
        }

        return ['host' => $host, 'port' => $port, 'addresses' => $addresses];
    }
}
