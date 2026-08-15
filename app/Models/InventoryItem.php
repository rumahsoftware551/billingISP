<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class InventoryItem extends Model {
    use BelongsToTenant;
    protected $fillable=['tenant_id','inventory_sku_id','current_location_id','supplier_id','purchase_order_id','asset_code','category','brand','model','serial_number','mac_address','barcode','status','condition','acquisition_cost','assigned_customer_service_id','assigned_technician_id','purchase_date','warranty_until','installed_at','retrieved_at','notes','metadata'];
    protected function casts():array{return ['purchase_date'=>'date','warranty_until'=>'date','installed_at'=>'datetime','retrieved_at'=>'datetime','acquisition_cost'=>'decimal:2','metadata'=>'array'];}
    public function sku(){return $this->belongsTo(InventorySku::class,'inventory_sku_id');}
    public function location(){return $this->belongsTo(InventoryLocation::class,'current_location_id');}
    public function supplier(){return $this->belongsTo(InventorySupplier::class,'supplier_id');}
    public function service(){return $this->belongsTo(CustomerService::class,'assigned_customer_service_id');}
    public function technician(){return $this->belongsTo(Technician::class,'assigned_technician_id');}
    public function movements(){return $this->hasMany(InventoryMovement::class);}
}
