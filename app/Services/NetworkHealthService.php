<?php

namespace App\Services;

use App\Models\CustomerService;
use App\Models\NetworkNas;
use App\Models\RadiusActionLog;
use App\Models\Router;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Throwable;

class NetworkHealthService
{
    public function __construct(private readonly MikrotikRestClient $mikrotik) {}

    /** @return array<string,int|array<int,array<string,mixed>>> */
    public function snapshot(Tenant $tenant, bool $probeRouters = false): array
    {
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));

        $routerResults = [];
        $routers = Router::query()->orderBy('id')->get();
        foreach ($routers as $router) {
            if ($probeRouters) {
                try {
                    $resource = $this->mikrotik->systemResource($router);
                    $router->forceFill([
                        'status' => 'online',
                        'routeros_version' => $resource['version'] ?? $router->routeros_version,
                        'board_name' => $resource['board-name'] ?? $resource['board_name'] ?? $router->board_name,
                        'last_seen_at' => now(),
                        'last_error' => null,
                    ])->save();
                } catch (Throwable $e) {
                    $router->forceFill([
                        'status' => 'offline',
                        'last_error' => mb_substr($e->getMessage(), 0, 1500),
                    ])->save();
                }
            }

            $routerResults[] = [
                'id' => $router->id,
                'name' => $router->name,
                'host' => $router->host,
                'status' => $router->status,
                'last_seen_at' => $router->last_seen_at?->toIso8601String(),
                'last_error' => $router->last_error,
            ];
        }

        $nasProjectionMismatch = 0;
        foreach (NetworkNas::query()->orderBy('id')->get() as $nas) {
            try {
                $projection = DB::table('nas')->where('nasname', $nas->nasname)->first();
                $matches = $projection
                    && (string) $projection->shortname === (string) $nas->shortname
                    && (string) $projection->type === (string) $nas->type
                    && (string) $projection->secret === (string) $nas->secret;
                if (! $matches) {
                    $nasProjectionMismatch++;
                }
            } catch (Throwable) {
                $nasProjectionMismatch++;
            }
        }

        $activeProjectionDrift = 0;
        CustomerService::query()->where('status', 'active')->orderBy('id')->chunkById(100, function ($services) use (&$activeProjectionDrift) {
            foreach ($services as $service) {
                $hasPassword = DB::table('radcheck')
                    ->where('username', $service->pppoe_username)
                    ->where('attribute', 'Cleartext-Password')
                    ->where('op', ':=')
                    ->exists();
                $hasReject = DB::table('radcheck')
                    ->where('username', $service->pppoe_username)
                    ->where('attribute', 'Auth-Type')
                    ->whereRaw('LOWER(value) = ?', ['reject'])
                    ->exists();
                if (! $hasPassword || $hasReject) {
                    $activeProjectionDrift++;
                }
            }
        });

        $suspendedProjectionDrift = 0;
        CustomerService::query()->where('status', 'suspended')->orderBy('id')->chunkById(100, function ($services) use (&$suspendedProjectionDrift) {
            foreach ($services as $service) {
                $hasReject = DB::table('radcheck')
                    ->where('username', $service->pppoe_username)
                    ->where('attribute', 'Auth-Type')
                    ->whereRaw('LOWER(value) = ?', ['reject'])
                    ->exists();
                $hasReply = DB::table('radreply')->where('username', $service->pppoe_username)->exists()
                    || DB::table('radusergroup')->where('username', $service->pppoe_username)->exists();
                if (! $hasReject || $hasReply) {
                    $suspendedProjectionDrift++;
                }
            }
        });

        $online = DB::table('radacct as r')
            ->join('customer_services as s', function ($join) use ($tenant) {
                $join->on('s.pppoe_username', '=', 'r.username')
                    ->where('s.tenant_id', '=', $tenant->id);
            })
            ->whereNull('r.acctstoptime');

        $onlineCount = (clone $online)->count();
        $staleMinutes = max(5, (int) config('jaringanku.network_stale_minutes', 15));
        $staleCount = (clone $online)
            ->whereRaw('COALESCE(r.acctupdatetime, r.acctstarttime) < ?', [now()->subMinutes($staleMinutes)])
            ->count();

        $failedActions = RadiusActionLog::query()
            ->where('success', false)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->count();

        return [
            'routers_total' => $routers->count(),
            'routers_online' => $routers->where('status', 'online')->count(),
            'routers_offline' => $routers->where('status', 'offline')->count(),
            'routers_unknown' => $routers->whereNotIn('status', ['online', 'offline'])->count(),
            'nas_total' => NetworkNas::query()->count(),
            'nas_projection_mismatch' => $nasProjectionMismatch,
            'active_projection_drift' => $activeProjectionDrift,
            'suspended_projection_drift' => $suspendedProjectionDrift,
            'online_sessions' => $onlineCount,
            'stale_sessions' => $staleCount,
            'failed_radius_actions_15m' => $failedActions,
            'routers' => $routerResults,
        ];
    }
}
