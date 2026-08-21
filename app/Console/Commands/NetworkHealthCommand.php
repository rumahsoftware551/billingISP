<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\NetworkHealthService;
use Illuminate\Console\Command;

class NetworkHealthCommand extends Command
{
    protected $signature = 'jaringanku:network-health {--tenant= : Optional tenant slug} {--probe-routers : Probe MikroTik RouterOS endpoints} {--strict : Fail on projection drift or router probe failure}';
    protected $description = 'Inspect tenant network health, RADIUS projections, accounting freshness, and optional MikroTik reachability.';

    public function handle(NetworkHealthService $health): int
    {
        $query = Tenant::query()->where('status', 'active');
        if ($slug = $this->option('tenant')) {
            $query->where('slug', $slug);
        }

        $tenants = $query->get();
        if ($tenants->isEmpty()) {
            $this->info('Tidak ada tenant aktif untuk network health check.');
            return self::SUCCESS;
        }

        $failed = false;
        foreach ($tenants as $tenant) {
            $snapshot = $health->snapshot($tenant, (bool) $this->option('probe-routers'));
            $this->newLine();
            $this->info('Tenant: '.$tenant->slug);
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Routers online / total', $snapshot['routers_online'].' / '.$snapshot['routers_total']],
                    ['Routers offline', $snapshot['routers_offline']],
                    ['NAS projection mismatch', $snapshot['nas_projection_mismatch']],
                    ['Active RADIUS drift', $snapshot['active_projection_drift']],
                    ['Suspended RADIUS drift', $snapshot['suspended_projection_drift']],
                    ['Online sessions', $snapshot['online_sessions']],
                    ['Stale sessions', $snapshot['stale_sessions']],
                    ['Failed CoA/Disconnect 15m', $snapshot['failed_radius_actions_15m']],
                ]
            );

            if ($this->option('strict')) {
                $projectionFailure = $snapshot['nas_projection_mismatch'] > 0
                    || $snapshot['active_projection_drift'] > 0
                    || $snapshot['suspended_projection_drift'] > 0;
                $routerFailure = $this->option('probe-routers') && $snapshot['routers_offline'] > 0;
                $failed = $failed || $projectionFailure || $routerFailure;
            }
        }

        if ($failed) {
            $this->error('NETWORK HEALTH STRICT GATE FAILED');
            return self::FAILURE;
        }

        $this->info('NETWORK HEALTH CHECK PASSED');
        return self::SUCCESS;
    }
}
