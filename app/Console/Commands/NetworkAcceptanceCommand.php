<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\NetworkHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class NetworkAcceptanceCommand extends Command
{
    protected $signature = 'jaringanku:network-acceptance {--tenant= : Optional tenant slug} {--strict : Fail on configuration/projection drift} {--live : Probe MikroTik routers and require them online}';
    protected $description = 'Run the Phase 04 network commercial-readiness gate without sending destructive CoA/disconnect packets.';

    public function handle(NetworkHealthService $health): int
    {
        foreach (['routers', 'network_nas', 'nas', 'radcheck', 'radreply', 'radacct', 'radius_action_logs', 'customer_services'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error('Missing network table: '.$table);
                return self::FAILURE;
            }
        }

        foreach (['network.index', 'network.routers.test', 'network.radius.test', 'network.sessions.index', 'network.sessions.disconnect', 'network.sessions.coa'] as $route) {
            if (! Route::has($route)) {
                $this->error('Missing network route: '.$route);
                return self::FAILURE;
            }
        }

        if ($this->option('strict') && trim((string) config('jaringanku.radius_client_network', 'disabled')) === 'disabled') {
            $this->error('RADIUS_CLIENT_NETWORK masih disabled.');
            return self::FAILURE;
        }

        $query = Tenant::query()->where('status', 'active');
        if ($slug = $this->option('tenant')) {
            $query->where('slug', $slug);
        }
        $tenants = $query->get();

        $failed = false;
        foreach ($tenants as $tenant) {
            $snapshot = $health->snapshot($tenant, (bool) $this->option('live'));
            $projectionFailure = $snapshot['nas_projection_mismatch'] > 0
                || $snapshot['active_projection_drift'] > 0
                || $snapshot['suspended_projection_drift'] > 0;
            $liveFailure = $this->option('live') && $snapshot['routers_total'] > 0 && $snapshot['routers_offline'] > 0;

            if ($projectionFailure || $liveFailure) {
                $failed = true;
                $this->error(sprintf(
                    '%s FAILED: nas_drift=%d active_drift=%d suspended_drift=%d routers_offline=%d',
                    $tenant->slug,
                    $snapshot['nas_projection_mismatch'],
                    $snapshot['active_projection_drift'],
                    $snapshot['suspended_projection_drift'],
                    $snapshot['routers_offline'],
                ));
            } else {
                $this->info($tenant->slug.' network acceptance: PASS');
            }
        }

        if ($failed && $this->option('strict')) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('PHASE 04 NETWORK ACCEPTANCE GATE PASSED');
        if (! $this->option('live')) {
            $this->line('Live router/RADIUS packet acceptance is intentionally deferred to VPS/hardware acceptance.');
        }
        return self::SUCCESS;
    }
}
