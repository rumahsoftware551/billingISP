<?php
namespace App\Console\Commands;
use App\Models\CustomerService;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryPortalAccount;
use App\Models\InventorySku;
use App\Models\Tenant;
use App\Services\InventoryLedgerService;
use App\Support\CurrentTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class Phase14SmokeCommand extends Command {
 protected $signature='jaringanku:phase14-smoke'; protected $description='Validate stock ledger, transfer, serialized asset install/retrieve, and stock opname.';
 public function handle():int {
  $tenant=Tenant::query()->where('slug',env('SEED_TENANT_SLUG','demo-isp'))->first()?:Tenant::query()->first(); if(!$tenant){$this->error('Tenant tidak ditemukan.');return self::FAILURE;} app()->instance(CurrentTenant::class,new CurrentTenant($tenant));
  $demo=InventoryPortalAccount::query()->where('email','inventory@jaringanku.local')->first(); if(!$demo && !filter_var(env('SEED_DEMO_DATA',false),FILTER_VALIDATE_BOOL)){$this->warn('SEED_DEMO_DATA=false; Phase 14 smoke dilewati setelah preflight.');return self::SUCCESS;} $password=(string)env('PHASE14_INVENTORY_PASSWORD',''); if($password==='' || !$demo || !$demo->passwordMatches($password)){$this->error('Demo Inventory account/password belum valid.');return 2;}
  $service=CustomerService::query()->first(); if(!$service){$this->error('Customer service demo tidak tersedia.');return 3;}
  DB::beginTransaction(); try {
   $s=Str::upper(Str::random(7)); $wh=InventoryLocation::create(['code'=>'WH-'.$s,'name'=>'Smoke Warehouse','location_type'=>'warehouse','active'=>true]); $tech=InventoryLocation::create(['code'=>'TECH-'.$s,'name'=>'Smoke Technician Stock','location_type'=>'technician','active'=>true]);
   $bulk=InventorySku::create(['sku'=>'CABLE-'.$s,'name'=>'Drop Cable Smoke','category'=>'cable','uom'=>'m','minimum_stock'=>5,'serialized'=>false,'active'=>true]); $ont=InventorySku::create(['sku'=>'ONT-'.$s,'name'=>'ONT Smoke','category'=>'ont','brand'=>'Smoke','model'=>'X1','uom'=>'pcs','minimum_stock'=>1,'serialized'=>true,'track_mac'=>true,'active'=>true]); $ledger=app(InventoryLedgerService::class);
   $this->info('1/5 Receive bulk stock...'); $ledger->receive($wh,$bulk,10,1000,[],null,null,$demo->id); if((float)InventoryBalance::where('inventory_location_id',$wh->id)->where('inventory_sku_id',$bulk->id)->value('quantity_on_hand')!==10.0){$this->error('Receive balance gagal.');return 4;}
   $this->info('2/5 Transfer stok ke lokasi teknisi...'); $ledger->transfer($wh,$tech,$bulk,3,[],$demo->id); $w=(float)InventoryBalance::where('inventory_location_id',$wh->id)->where('inventory_sku_id',$bulk->id)->value('quantity_on_hand');$t=(float)InventoryBalance::where('inventory_location_id',$tech->id)->where('inventory_sku_id',$bulk->id)->value('quantity_on_hand');if($w!==7.0||$t!==3.0){$this->error('Transfer balance gagal.');return 5;}
   $this->info('3/5 Receive serialized asset SN/MAC...'); $macHex=strtoupper(substr(hash('sha256',$s),0,6)); $mac='02:00:00:'.substr($macHex,0,2).':'.substr($macHex,2,2).':'.substr($macHex,4,2); $ledger->receive($wh,$ont,1,250000,[['serial_number'=>'SN-'.$s,'mac_address'=>$mac]],null,null,$demo->id); $asset=InventoryItem::query()->where('inventory_sku_id',$ont->id)->where('serial_number','SN-'.$s)->first(); if(!$asset){$this->error('Serialized asset gagal dibuat.');return 6;}
   $this->info('4/5 Transfer asset -> install customer -> retrieve...'); $ledger->transfer($wh,$tech,$ont,1,[$asset->id],$demo->id); $asset->refresh(); if((int)$asset->current_location_id!==(int)$tech->id){$this->error('Asset transfer gagal.');return 7;} $ledger->installAsset($asset,$service,$demo->id); $asset->refresh(); if($asset->status!=='assigned_customer'||(int)$asset->assigned_customer_service_id!==(int)$service->id){$this->error('Asset install gagal.');return 8;} $ledger->retrieveAsset($asset,$wh,$demo->id,null,'good'); $asset->refresh(); if($asset->status!=='available'||(int)$asset->current_location_id!==(int)$wh->id){$this->error('Asset retrieve gagal.');return 9;}
   $this->info('5/5 Stock opname adjustment...'); $ledger->stockOpname($tech,$bulk,2,$demo->id,'Phase 14 smoke'); $after=(float)InventoryBalance::where('inventory_location_id',$tech->id)->where('inventory_sku_id',$bulk->id)->value('quantity_on_hand'); if($after!==2.0){$this->error('Stock opname gagal.');return 10;}
   $this->newLine();$this->info('PHASE 14 INVENTORY PORTAL SMOKE TEST PASSED');$this->line('Receive & weighted balance : PASS');$this->line('Warehouse transfer         : PASS');$this->line('Serialized SN/MAC asset     : PASS');$this->line('Install/retrieve customer   : PASS');$this->line('Stock opname variance       : PASS'); return self::SUCCESS;
  } finally { DB::rollBack(); }
 }
}
