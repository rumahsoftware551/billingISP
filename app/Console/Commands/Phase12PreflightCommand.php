<?php
namespace App\Console\Commands;

use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Services\SaasPlanService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class Phase12PreflightCommand extends Command
{
    protected $signature = 'jaringanku:phase12-preflight';
    protected $description = 'Validate Phase 12 SaaS schema, routes, subscription and release metadata.';

    public function handle(SaasPlanService $saas): int
    {
        foreach (['platform_plans','tenant_subscriptions','platform_events','release_records'] as $table) {
            if (! Schema::hasTable($table)) { $this->error("Missing table: {$table}"); return self::FAILURE; }
        }
        foreach (['platform.index','platform.tenants.store','platform.tenants.subscription','platform.plans.update','version'] as $route) {
            if (! Route::has($route)) { $this->error("Missing route: {$route}"); return self::FAILURE; }
        }
        if (PlatformPlan::where('active',true)->count() < 3) { $this->error('Expected at least three active SaaS plans.'); return self::FAILURE; }
        $tenant = Tenant::where('slug', config('jaringanku.seed_tenant_slug'))->first();
        if ($tenant && ! TenantSubscription::where('tenant_id',$tenant->id)->exists()) { $this->error('Seed tenant has no subscription.'); return self::FAILURE; }
        if ($tenant && ! $saas->summary($tenant)['usable']) { $this->error('Seed tenant subscription is not usable.'); return self::FAILURE; }
        if (! version_compare((string) config('jaringanku.version'), '1.0.0', '>=')) { $this->error('Product version must be >= 1.0.0.'); return self::FAILURE; }
        $this->info('PHASE 12 PREFLIGHT PASSED');
        return self::SUCCESS;
    }
}
