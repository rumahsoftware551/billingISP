<?php

namespace App\Console\Commands;

use App\Models\CustomPaymentMethod;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantBranding;
use App\Services\PermissionService;
use App\Support\CurrentTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Phase16SmokeCommand extends Command
{
    protected $signature = 'jaringanku:phase16-smoke';
    protected $description = 'Run Phase 16 transactional smoke tests.';

    public function handle(): int
    {
        $tenant=Tenant::query()->where('slug',config('jaringanku.seed_tenant_slug','demo-isp'))->firstOrFail();
        app()->instance(CurrentTenant::class,new CurrentTenant($tenant));

        DB::beginTransaction();
        try {
            $branding=TenantBranding::query()->where('tenant_id',$tenant->id)->firstOrFail();
            $old=$branding->app_name;
            $branding->forceFill(['app_name'=>'Jaringanku Phase16 Smoke'])->save();
            if(TenantBranding::query()->where('tenant_id',$tenant->id)->value('app_name')!=='Jaringanku Phase16 Smoke') throw new \RuntimeException('Branding update smoke gagal.');
            $branding->forceFill(['app_name'=>$old])->save();

            $code='smoke-payment-'.strtolower(substr(bin2hex(random_bytes(4)),0,8));
            $method=CustomPaymentMethod::create(['code'=>$code,'name'=>'Smoke Payment','type'=>'custom','admin_fee_type'=>'none','admin_fee_value'=>0,'minimum_amount'=>0,'customer_visible'=>true,'partner_visible'=>false,'active'=>true,'sort_order'=>999]);
            if((string)$method->tenant_id!==(string)$tenant->id) throw new \RuntimeException('Payment method tenant projection gagal.');

            $permissionId=DB::table('permissions')->where('slug','customers.view')->value('id');
            $role=Role::create(['tenant_id'=>$tenant->id,'name'=>'Phase16 Smoke Role','slug'=>'phase16-smoke-role','description'=>'transactional smoke']);
            $role->permissions()->sync([$permissionId]);
            if(!$role->permissions()->where('slug','customers.view')->exists()) throw new \RuntimeException('RBAC role sync gagal.');

            if(app()->environment('local') && filter_var(env('SEED_DEMO_DATA',false),FILTER_VALIDATE_BOOL)){
                if(!DB::table('partner_accounts')->where('tenant_id',$tenant->id)->where('email','mitra@jaringanku.local')->exists()) throw new \RuntimeException('Demo portal mitra tidak tersedia.');
                if(!DB::table('inventory_portal_accounts')->where('tenant_id',$tenant->id)->where('email','inventory@jaringanku.local')->exists()) throw new \RuntimeException('Demo portal inventory tidak tersedia.');
            }

            DB::rollBack();
            $this->info('PHASE 16 COMMERCIAL READINESS SMOKE TEST PASSED');
            $this->line('Branding + custom payment + RBAC + portal account readiness + rollback valid.');
            return self::SUCCESS;
        } catch(\Throwable $e){
            if(DB::transactionLevel()>0) DB::rollBack();
            $this->error('Phase 16 smoke gagal: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}
