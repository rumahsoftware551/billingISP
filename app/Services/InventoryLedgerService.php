<?php
namespace App\Services;

use App\Models\CustomerService;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventorySku;
use App\Models\InventoryStockOpname;
use App\Models\InventoryTransaction;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryLedgerService
{
    public function __construct(private TenantSequenceService $sequence) {}

    private function tenantId(): string { return app(CurrentTenant::class)->id(); }

    private function transactionNumber(): string
    {
        return $this->sequence->next($this->tenantId(), 'inventory_transaction', 'INV-MOV-', 7);
    }

    private function balance(int $locationId, int $skuId, bool $lock = true): InventoryBalance
    {
        InventoryBalance::query()->firstOrCreate(
            ['inventory_location_id'=>$locationId,'inventory_sku_id'=>$skuId],
            ['quantity_on_hand'=>0,'quantity_reserved'=>0,'average_cost'=>0]
        );
        $q=InventoryBalance::query()->where('inventory_location_id',$locationId)->where('inventory_sku_id',$skuId);
        if($lock)$q->lockForUpdate();
        return $q->firstOrFail();
    }

    private function adjust(int $locationId,int $skuId,float $delta,float $unitCost=0): InventoryBalance
    {
        $balance=$this->balance($locationId,$skuId,true);
        $before=(float)$balance->quantity_on_hand;
        $after=$before+$delta;
        if($after < -0.0001) throw ValidationException::withMessages(['quantity'=>'Stok tidak mencukupi. Tersedia '.number_format($before,3,',','.').'.']);
        $average=(float)$balance->average_cost;
        if($delta>0 && $unitCost>0){
            $value=($before*$average)+($delta*$unitCost);
            $average=$after>0 ? $value/$after : 0;
        }
        $balance->update(['quantity_on_hand'=>$after,'average_cost'=>$average]);
        return $balance->fresh();
    }

    public function receive(InventoryLocation $to, InventorySku $sku, float $qty, float $unitCost=0, array $assets=[], ?int $supplierId=null, ?int $purchaseOrderId=null, ?int $actorAccountId=null, ?int $actorUserId=null, ?string $notes=null): InventoryTransaction
    {
        if($qty<=0) throw ValidationException::withMessages(['quantity'=>'Jumlah penerimaan harus lebih dari 0.']);
        if($sku->serialized && floor($qty)!==$qty) throw ValidationException::withMessages(['quantity'=>'Quantity SKU serialized harus bilangan bulat.']);
        if($sku->serialized && count($assets)!==(int)$qty) throw ValidationException::withMessages(['assets'=>'SKU serialized membutuhkan data asset sebanyak quantity penerimaan.']);
        if(!$sku->serialized && $assets) throw ValidationException::withMessages(['assets'=>'Asset detail hanya boleh untuk SKU serialized.']);
        return DB::transaction(function()use($to,$sku,$qty,$unitCost,$assets,$supplierId,$purchaseOrderId,$actorAccountId,$actorUserId,$notes){
            $tx=InventoryTransaction::create(['transaction_number'=>$this->transactionNumber(),'transaction_type'=>'receive','status'=>'posted','to_location_id'=>$to->id,'supplier_id'=>$supplierId,'purchase_order_id'=>$purchaseOrderId,'actor_inventory_account_id'=>$actorAccountId,'actor_user_id'=>$actorUserId,'notes'=>$notes,'occurred_at'=>now()]);
            $this->adjust($to->id,$sku->id,$qty,$unitCost);
            if($sku->serialized){
                foreach($assets as $row){
                    $serial=trim((string)($row['serial_number']??'')); $mac=trim((string)($row['mac_address']??''));
                    if($serial==='') throw ValidationException::withMessages(['assets'=>'Serial number wajib untuk asset serialized.']);
                    if(InventoryItem::query()->where('serial_number',$serial)->exists()) throw ValidationException::withMessages(['assets'=>'Serial number '.$serial.' sudah terdaftar.']);
                    if($mac!=='' && InventoryItem::query()->where('mac_address',$mac)->exists()) throw ValidationException::withMessages(['assets'=>'MAC address '.$mac.' sudah terdaftar.']);
                    $asset=InventoryItem::create([
                        'inventory_sku_id'=>$sku->id,'current_location_id'=>$to->id,'supplier_id'=>$supplierId,'purchase_order_id'=>$purchaseOrderId,
                        'asset_code'=>$this->sequence->next($this->tenantId(),'inventory','AST-',7),'category'=>$sku->category,'brand'=>$sku->brand,'model'=>$sku->model,
                        'serial_number'=>$serial,'mac_address'=>$mac!==''?$mac:null,'barcode'=>trim((string)($row['barcode']??''))?:null,'status'=>'available','condition'=>'good','acquisition_cost'=>$unitCost,
                        'purchase_date'=>now()->toDateString(),'notes'=>$notes
                    ]);
                    $tx->lines()->create(['inventory_sku_id'=>$sku->id,'inventory_item_id'=>$asset->id,'quantity'=>1,'unit_cost'=>$unitCost]);
                    $asset->movements()->create(['inventory_transaction_id'=>$tx->id,'inventory_sku_id'=>$sku->id,'to_location_id'=>$to->id,'quantity'=>1,'movement_type'=>'receive','to_status'=>'available','actor_user_id'=>$actorUserId,'notes'=>$notes]);
                }
            } else {
                $tx->lines()->create(['inventory_sku_id'=>$sku->id,'quantity'=>$qty,'unit_cost'=>$unitCost]);
            }
            return $tx->fresh(['lines']);
        });
    }

    public function transfer(InventoryLocation $from, InventoryLocation $to, InventorySku $sku, float $qty, array $assetIds=[], ?int $actorAccountId=null, ?int $actorUserId=null, ?string $notes=null, string $type='transfer'): InventoryTransaction
    {
        if($from->id===$to->id) throw ValidationException::withMessages(['to_location_id'=>'Lokasi tujuan harus berbeda.']);
        if($qty<=0) throw ValidationException::withMessages(['quantity'=>'Jumlah transfer harus lebih dari 0.']);
        if($sku->serialized && floor($qty)!==$qty) throw ValidationException::withMessages(['quantity'=>'Quantity SKU serialized harus bilangan bulat.']);
        if($sku->serialized && count($assetIds)!==(int)$qty) throw ValidationException::withMessages(['asset_ids'=>'Pilih asset serialized sesuai quantity.']);
        return DB::transaction(function()use($from,$to,$sku,$qty,$assetIds,$actorAccountId,$actorUserId,$notes,$type){
            $sourceBalance=$this->balance($from->id,$sku->id,true);
            $transferCost=(float)$sourceBalance->average_cost;
            if((float)$sourceBalance->quantity_on_hand + 0.0001 < $qty) throw ValidationException::withMessages(['quantity'=>'Stok lokasi sumber tidak mencukupi.']);
            $this->adjust($from->id,$sku->id,-$qty);
            $this->adjust($to->id,$sku->id,$qty,$transferCost);
            $tx=InventoryTransaction::create(['transaction_number'=>$this->transactionNumber(),'transaction_type'=>$type,'status'=>'posted','from_location_id'=>$from->id,'to_location_id'=>$to->id,'actor_inventory_account_id'=>$actorAccountId,'actor_user_id'=>$actorUserId,'notes'=>$notes,'occurred_at'=>now()]);
            if($sku->serialized){
                $assets=InventoryItem::query()->whereIn('id',$assetIds)->where('inventory_sku_id',$sku->id)->where('current_location_id',$from->id)->lockForUpdate()->get();
                if($assets->count()!==count($assetIds)) throw ValidationException::withMessages(['asset_ids'=>'Ada asset yang tidak berada di lokasi sumber.']);
                foreach($assets as $asset){
                    $status=$to->location_type==='technician'?'assigned_technician':'available';
                    $asset->update(['current_location_id'=>$to->id,'assigned_technician_id'=>$to->technician_id,'assigned_customer_service_id'=>null,'status'=>$status]);
                    $tx->lines()->create(['inventory_sku_id'=>$sku->id,'inventory_item_id'=>$asset->id,'quantity'=>1,'unit_cost'=>$asset->acquisition_cost]);
                    $asset->movements()->create(['inventory_transaction_id'=>$tx->id,'inventory_sku_id'=>$sku->id,'from_location_id'=>$from->id,'to_location_id'=>$to->id,'quantity'=>1,'movement_type'=>$type,'from_status'=>'available','to_status'=>$status,'technician_id'=>$to->technician_id,'actor_user_id'=>$actorUserId,'notes'=>$notes]);
                }
            } else $tx->lines()->create(['inventory_sku_id'=>$sku->id,'quantity'=>$qty]);
            return $tx->fresh(['lines']);
        });
    }

    public function installAsset(InventoryItem $asset, CustomerService $service, ?int $actorAccountId=null, ?int $actorUserId=null, ?string $notes=null): InventoryTransaction
    {
        if(!$asset->inventory_sku_id || !$asset->current_location_id) throw ValidationException::withMessages(['asset_id'=>'Asset tidak berada dalam stok lokasi.']);
        return DB::transaction(function()use($asset,$service,$actorAccountId,$actorUserId,$notes){
            $asset=InventoryItem::query()->lockForUpdate()->findOrFail($asset->id);
            $fromId=(int)$asset->current_location_id; $skuId=(int)$asset->inventory_sku_id;
            $this->adjust($fromId,$skuId,-1);
            $tx=InventoryTransaction::create(['transaction_number'=>$this->transactionNumber(),'transaction_type'=>'install','status'=>'posted','from_location_id'=>$fromId,'customer_service_id'=>$service->id,'actor_inventory_account_id'=>$actorAccountId,'actor_user_id'=>$actorUserId,'notes'=>$notes,'occurred_at'=>now()]);
            $fromStatus=$asset->status;
            $asset->update(['current_location_id'=>null,'assigned_customer_service_id'=>$service->id,'assigned_technician_id'=>null,'status'=>'assigned_customer','installed_at'=>now(),'retrieved_at'=>null]);
            $tx->lines()->create(['inventory_sku_id'=>$skuId,'inventory_item_id'=>$asset->id,'quantity'=>1,'unit_cost'=>$asset->acquisition_cost]);
            $asset->movements()->create(['inventory_transaction_id'=>$tx->id,'inventory_sku_id'=>$skuId,'from_location_id'=>$fromId,'quantity'=>1,'movement_type'=>'install','from_status'=>$fromStatus,'to_status'=>'assigned_customer','customer_service_id'=>$service->id,'actor_user_id'=>$actorUserId,'notes'=>$notes]);
            return $tx;
        });
    }

    public function retrieveAsset(InventoryItem $asset, InventoryLocation $to, ?int $actorAccountId=null, ?int $actorUserId=null, ?string $condition='good', ?string $notes=null): InventoryTransaction
    {
        if(!$asset->inventory_sku_id || !$asset->assigned_customer_service_id) throw ValidationException::withMessages(['asset_id'=>'Asset tidak sedang terpasang pada customer.']);
        return DB::transaction(function()use($asset,$to,$actorAccountId,$actorUserId,$condition,$notes){
            $asset=InventoryItem::query()->lockForUpdate()->findOrFail($asset->id); $skuId=(int)$asset->inventory_sku_id; $serviceId=(int)$asset->assigned_customer_service_id;
            $this->adjust($to->id,$skuId,1,(float)$asset->acquisition_cost);
            $tx=InventoryTransaction::create(['transaction_number'=>$this->transactionNumber(),'transaction_type'=>'retrieve','status'=>'posted','to_location_id'=>$to->id,'customer_service_id'=>$serviceId,'actor_inventory_account_id'=>$actorAccountId,'actor_user_id'=>$actorUserId,'notes'=>$notes,'occurred_at'=>now()]);
            $status=$condition==='good'?'available':($condition==='repair'?'repair':'damaged');
            $asset->update(['current_location_id'=>$to->id,'assigned_customer_service_id'=>null,'assigned_technician_id'=>null,'status'=>$status,'condition'=>$condition,'retrieved_at'=>now()]);
            $tx->lines()->create(['inventory_sku_id'=>$skuId,'inventory_item_id'=>$asset->id,'quantity'=>1,'unit_cost'=>$asset->acquisition_cost]);
            $asset->movements()->create(['inventory_transaction_id'=>$tx->id,'inventory_sku_id'=>$skuId,'to_location_id'=>$to->id,'quantity'=>1,'movement_type'=>'retrieve','from_status'=>'assigned_customer','to_status'=>$status,'customer_service_id'=>$serviceId,'actor_user_id'=>$actorUserId,'notes'=>$notes]);
            return $tx;
        });
    }

    public function stockOpname(InventoryLocation $location, InventorySku $sku, float $counted, ?int $actorAccountId=null, ?string $notes=null): InventoryStockOpname
    {
        if($counted<0) throw ValidationException::withMessages(['counted_quantity'=>'Jumlah fisik tidak boleh negatif.']);
        if($sku->serialized) throw ValidationException::withMessages(['inventory_sku_id'=>'Stock opname SKU serialized dilakukan melalui rekonsiliasi asset SN/MAC, bukan adjustment quantity langsung.']);
        return DB::transaction(function()use($location,$sku,$counted,$actorAccountId,$notes){
            $balance=$this->balance($location->id,$sku->id,true); $system=(float)$balance->quantity_on_hand; $variance=$counted-$system;
            $opname=InventoryStockOpname::create(['opname_number'=>$this->sequence->next($this->tenantId(),'inventory_opname','OPN-',7),'inventory_location_id'=>$location->id,'created_by_inventory_account_id'=>$actorAccountId,'status'=>'posted','counted_at'=>now(),'notes'=>$notes]);
            $opname->lines()->create(['inventory_sku_id'=>$sku->id,'system_quantity'=>$system,'counted_quantity'=>$counted,'variance_quantity'=>$variance,'notes'=>$notes]);
            if(abs($variance)>0.0001){
                $balance->update(['quantity_on_hand'=>$counted]);
                $tx=InventoryTransaction::create(['transaction_number'=>$this->transactionNumber(),'transaction_type'=>'adjustment','status'=>'posted','to_location_id'=>$variance>0?$location->id:null,'from_location_id'=>$variance<0?$location->id:null,'actor_inventory_account_id'=>$actorAccountId,'notes'=>'Stock opname '.$opname->opname_number.($notes?' - '.$notes:''),'occurred_at'=>now()]);
                $tx->lines()->create(['inventory_sku_id'=>$sku->id,'quantity'=>$variance,'notes'=>'Stock opname variance']);
            }
            return $opname->fresh(['lines']);
        });
    }
}
