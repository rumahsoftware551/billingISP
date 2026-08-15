<?php

namespace App\Console\Commands;

use App\Models\CustomPaymentMethod;
use App\Models\Tenant;
use App\Models\TenantBranding;
use App\Support\CurrentTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class Phase16PreflightCommand extends Command
{
    protected $signature = 'jaringanku:phase16-preflight';
    protected $description = 'Validate Phase 16 commercial-readiness schema, routes, portal repair and configuration.';

    public function handle(): int
    {
        $version = trim((string) config('jaringanku.version'));
        $channel = trim((string) config('jaringanku.release_channel'));
        if ($version !== '1.2.0-dev') { $this->error("Phase 16 requires JARINGANKU_VERSION=1.2.0-dev. Current: {$version}"); return self::FAILURE; }
        if ($channel !== 'development') { $this->error("Phase 16 requires RELEASE_CHANNEL=development. Current: {$channel}"); return self::FAILURE; }

        $requiredTables=['tenant_brandings','custom_payment_methods','manual_payment_proofs','roles','permissions','permission_role','tenant_memberships'];
        foreach($requiredTables as $table){ if(!Schema::hasTable($table)){ $this->error("Missing table: {$table}"); return self::FAILURE; } }
        foreach(['status'] as $column){ if(!Schema::hasColumn('tenant_memberships',$column)){ $this->error("tenant_memberships.{$column} missing"); return self::FAILURE; } }
        foreach(['status','last_login_at','last_login_ip'] as $column){ if(!Schema::hasColumn('users',$column)){ $this->error("users.{$column} missing"); return self::FAILURE; } }

        $routeNames=collect(Route::getRoutes()->getRoutes())->map(fn($r)=>$r->getName())->filter()->all();
        foreach(['access.center','settings.index','billing.manual-payments.index','portal.invoices.manual-payment','partner.login','inventory.login'] as $name){
            if(!in_array($name,$routeNames,true)){ $this->error("Route missing: {$name}"); return self::FAILURE; }
        }

        $tenant=Tenant::query()->where('slug',config('jaringanku.seed_tenant_slug','demo-isp'))->first();
        if(!$tenant){ $this->error('Default tenant tidak ditemukan.'); return self::FAILURE; }
        app()->instance(CurrentTenant::class,new CurrentTenant($tenant));
        if(!TenantBranding::query()->where('tenant_id',$tenant->id)->exists()){ $this->error('Tenant branding baseline belum ada.'); return self::FAILURE; }
        if(!CustomPaymentMethod::query()->where('code','cash-loket')->exists()){ $this->error('Custom payment baseline belum ada.'); return self::FAILURE; }
        foreach(['dashboard.view','customers.view','billing.view','network.view','inventory.view','settings.manage'] as $permission){
            if(!DB::table('permissions')->where('slug',$permission)->exists()){ $this->error("Permission missing: {$permission}"); return self::FAILURE; }
        }

        $this->info('PHASE 16 COMMERCIAL READINESS PREFLIGHT PASSED');
        $this->line('Schema + access center + branding + custom payment + RBAC + portal routes valid.');
        return self::SUCCESS;
    }
}
