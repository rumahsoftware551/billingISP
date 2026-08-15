<?php
namespace App\Console\Commands;

use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Services\SaasPlanService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Phase12SmokeCommand extends Command
{
    protected $signature = 'jaringanku:phase12-smoke';
    protected $description = 'Run isolated SaaS and release smoke checks for Phase 12.';

    public function handle(SaasPlanService $saas): int
    {
        $plan = PlatformPlan::where('code','STARTER')->first();
        if (! $plan) { $this->error('STARTER plan missing.'); return self::FAILURE; }
        $tenant = null;
        try {
            DB::transaction(function () use (&$tenant, $plan, $saas) {
                $tenant = Tenant::create(['name'=>'Phase 12 Smoke','slug'=>'p12-'.Str::lower(Str::random(10)),'status'=>'active','timezone'=>'Asia/Jakarta','currency'=>'IDR']);
                TenantSubscription::create(['tenant_id'=>$tenant->id,'platform_plan_id'=>$plan->id,'status'=>'active','current_period_start'=>now(),'current_period_end'=>now()->addMonth()]);
                $summary = $saas->summary($tenant);
                if (! $summary['usable'] || $summary['plan']['code'] !== 'STARTER') throw new \RuntimeException('Subscription summary invalid.');
                foreach (['customers','services','routers','users'] as $resource) {
                    if (! array_key_exists($resource, $summary['usage'])) throw new \RuntimeException("Missing usage resource {$resource}.");
                }
                throw new \RuntimeException('__ROLLBACK_OK__');
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== '__ROLLBACK_OK__') { $this->error($e->getMessage()); return self::FAILURE; }
        }
        if ($tenant && Tenant::whereKey($tenant->id)->exists()) { $this->error('Smoke tenant was not rolled back.'); return self::FAILURE; }
        $this->info('PHASE 12 SAAS + PRODUCTION FINAL SMOKE TEST PASSED');
        return self::SUCCESS;
    }
}
