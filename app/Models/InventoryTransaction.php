<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class InventoryTransaction extends Model { use BelongsToTenant; protected $fillable=['tenant_id','transaction_number','transaction_type','status','from_location_id','to_location_id','supplier_id','purchase_order_id','work_order_id','customer_service_id','actor_user_id','actor_inventory_account_id','notes','occurred_at']; protected function casts():array{return ['occurred_at'=>'datetime'];} public function lines(){return $this->hasMany(InventoryTransactionLine::class);} public function fromLocation(){return $this->belongsTo(InventoryLocation::class,'from_location_id');} public function toLocation(){return $this->belongsTo(InventoryLocation::class,'to_location_id');} }
