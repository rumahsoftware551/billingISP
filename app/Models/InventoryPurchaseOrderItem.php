<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class InventoryPurchaseOrderItem extends Model { use BelongsToTenant; protected $fillable=['tenant_id','purchase_order_id','inventory_sku_id','quantity','received_quantity','unit_cost']; protected function casts():array{return ['quantity'=>'decimal:3','received_quantity'=>'decimal:3','unit_cost'=>'decimal:2'];} public function sku(){return $this->belongsTo(InventorySku::class,'inventory_sku_id');} }
