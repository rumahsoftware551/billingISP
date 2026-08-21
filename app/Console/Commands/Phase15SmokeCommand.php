<?php
namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\InventoryLocation;
use App\Models\Partner;
use App\Models\Tenant;
use App\Services\ReleaseAcceptanceService;
use App\Support\CurrentTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Phase15SmokeCommand extends Command
{
    protected $signature='jaringanku:phase15-smoke';
    protected $description='Run the Phase 15 baseline integration, isolation, reconciliation, and release checks inside cumulative releases.';

    public function handle(ReleaseAcceptanceService $audit): int
    {
        $result=$audit->run(false,null,false,'Phase 15 smoke');
        if($result['summary']['failed']>0){foreach($result['findings'] as $f){if($f['status']==='fail')$this->error($f['check_key'].': '.$f['detail']);}return self::FAILURE;}

        $demo=Tenant::query()->where('slug',config('jaringanku.seed_tenant_slug','demo-isp'))->first()?:Tenant::query()->first();
        if(!$demo){$this->error('Tenant demo tidak ditemukan.');return 2;}

        DB::beginTransaction();
        try {
            $other=Tenant::create(['name'=>'Phase 15 Isolation Smoke','slug'=>'p15-'.Str::lower(Str::random(10)),'status'=>'active','timezone'=>'Asia/Jakarta','currency'=>'IDR']);
            $now=now();
            $customerId=DB::table('customers')->insertGetId(['tenant_id'=>$other->id,'customer_number'=>'P15-'.Str::upper(Str::random(8)),'name'=>'Cross Tenant Smoke','customer_type'=>'residential','status'=>'active','created_at'=>$now,'updated_at'=>$now]);
            $partnerId=DB::table('partners')->insertGetId(['tenant_id'=>$other->id,'code'=>'P15-'.Str::upper(Str::random(6)),'name'=>'Cross Tenant Partner','status'=>'active','created_at'=>$now,'updated_at'=>$now]);
            $locationId=DB::table('inventory_locations')->insertGetId(['tenant_id'=>$other->id,'code'=>'P15-'.Str::upper(Str::random(6)),'name'=>'Cross Tenant Warehouse','location_type'=>'warehouse','active'=>true,'created_at'=>$now,'updated_at'=>$now]);
            app()->instance(CurrentTenant::class,new CurrentTenant($demo));
            if(Customer::query()->whereKey($customerId)->exists()){throw new \RuntimeException('Customer global tenant scope leaked.');}
            if(Partner::query()->whereKey($partnerId)->exists()){throw new \RuntimeException('Partner global tenant scope leaked.');}
            if(InventoryLocation::query()->whereKey($locationId)->exists()){throw new \RuntimeException('Inventory global tenant scope leaked.');}
            $this->line('[PASS] Cross-tenant Eloquent scope: customer / partner / inventory');
        } catch(\Throwable $e) {
            $this->error($e->getMessage()); return 3;
        } finally {
            DB::rollBack(); app()->instance(CurrentTenant::class,new CurrentTenant($demo));
        }

        $this->info('PHASE 15 INTEGRATION + SECURITY + REGRESSION SMOKE TEST PASSED');
        $this->line('Release baseline        : PASS');
        $this->line('Portal/RBAC guards       : PASS');
        $this->line('Credential-at-rest audit : PASS');
        $this->line('Cross-tenant isolation   : PASS');
        $this->line('Billing reconciliation   : PASS');
        $this->line('Partner reconciliation   : PASS');
        $this->line('Inventory reconciliation : PASS');
        return self::SUCCESS;
    }
}
