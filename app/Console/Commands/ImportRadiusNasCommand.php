<?php

namespace App\Console\Commands;

use App\Models\NetworkNas;
use App\Models\Tenant;
use App\Services\RadiusProjectionService;
use App\Support\CurrentTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportRadiusNasCommand extends Command
{
    protected $signature = 'jaringanku:nas-import
        {--tenant= : Tenant slug}
        {--nas-ip= : Existing FreeRADIUS nas.nasname value}
        {--router-id= : Optional Jaringanku router id}
        {--coa-port=3799 : CoA/Disconnect port}';

    protected $description = 'Import an existing FreeRADIUS SQL NAS row into tenant-aware network_nas without changing its shared secret.';

    public function handle(RadiusProjectionService $projection): int
    {
        $slug = trim((string) $this->option('tenant'));
        $nasIp = trim((string) $this->option('nas-ip'));
        $coaPort = (int) $this->option('coa-port');

        if ($slug === '' || $nasIp === '') {
            $this->error('--tenant and --nas-ip are required.');
            return self::FAILURE;
        }

        if ($coaPort < 1 || $coaPort > 65535) {
            $this->error('CoA port tidak valid.');
            return self::FAILURE;
        }

        $tenant = Tenant::query()->where('slug', $slug)->first();
        if (! $tenant) {
            $this->error('Tenant tidak ditemukan: '.$slug);
            return self::FAILURE;
        }

        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));

        try {
            $raw = DB::table('nas')->where('nasname', $nasIp)->first();
            if (! $raw) {
                $this->error('FreeRADIUS NAS tidak ditemukan: '.$nasIp);
                return self::FAILURE;
            }

            $routerId = $this->option('router-id');
            if ($routerId !== null && $routerId !== '') {
                $routerExists = DB::table('routers')
                    ->where('id', (int) $routerId)
                    ->where('tenant_id', $tenant->id)
                    ->exists();

                if (! $routerExists) {
                    $this->error('Router id tidak ditemukan pada tenant ini.');
                    return self::FAILURE;
                }
            }

            $existing = NetworkNas::query()->where('nasname', $nasIp)->first();
            if ($existing && (string) $existing->tenant_id !== (string) $tenant->id) {
                $this->error('NAS IP sudah dimiliki tenant lain.');
                return self::FAILURE;
            }

            $nas = NetworkNas::query()->updateOrCreate(
                ['nasname' => $nasIp],
                [
                    'tenant_id' => $tenant->id,
                    'router_id' => $routerId !== null && $routerId !== '' ? (int) $routerId : null,
                    'shortname' => (string) ($raw->shortname ?: 'nas-'.$nasIp),
                    'type' => (string) ($raw->type ?: 'mikrotik'),
                    'secret' => (string) $raw->secret,
                    'coa_port' => $coaPort,
                    'active' => true,
                    'description' => (string) ($raw->description ?: 'Imported from FreeRADIUS SQL clients'),
                ],
            );

            $projection->syncNas($nas);

            $this->info('NAS imported to network_nas.');
            $this->line('id='.$nas->id);
            $this->line('nasname='.$nas->nasname);
            $this->line('shortname='.$nas->shortname);
            $this->line('router_id='.($nas->router_id ?? 'null'));
            $this->line('active='.($nas->active ? 'yes' : 'no'));

            return self::SUCCESS;
        } finally {
            app()->forgetInstance(CurrentTenant::class);
        }
    }
}
