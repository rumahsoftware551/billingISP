<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class InventoryMovement extends Model {
    use BelongsToTenant; public $timestamps=false;
    protected $fillable=['tenant_id','inventory_item_id','inventory_transaction_id','inventory_sku_id','from_location_id','to_location_id','quantity','movement_type','from_status','to_status','customer_service_id','technician_id','actor_user_id','reference_type','reference_id','notes','created_at'];
    protected function casts():array{return ['created_at'=>'datetime','quantity'=>'decimal:3'];}
    public function item(){return $this->belongsTo(InventoryItem::class,'inventory_item_id');}
    public function transaction(){return $this->belongsTo(InventoryTransaction::class,'inventory_transaction_id');}
}
