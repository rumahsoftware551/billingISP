<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class InventoryPurchaseOrder extends Model { use BelongsToTenant; protected $fillable=['tenant_id','po_number','supplier_id','destination_location_id','created_by_user_id','created_by_inventory_account_id','status','ordered_at','expected_at','total_amount','notes']; protected function casts():array{return ['ordered_at'=>'date','expected_at'=>'date','total_amount'=>'decimal:2'];} public function items(){return $this->hasMany(InventoryPurchaseOrderItem::class,'purchase_order_id');} public function supplier(){return $this->belongsTo(InventorySupplier::class,'supplier_id');} public function destination(){return $this->belongsTo(InventoryLocation::class,'destination_location_id');} }
