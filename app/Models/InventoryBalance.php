<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class InventoryBalance extends Model { use BelongsToTenant; protected $fillable=['tenant_id','inventory_location_id','inventory_sku_id','quantity_on_hand','quantity_reserved','average_cost']; protected function casts():array{return ['quantity_on_hand'=>'decimal:3','quantity_reserved'=>'decimal:3','average_cost'=>'decimal:2'];} public function location(){return $this->belongsTo(InventoryLocation::class,'inventory_location_id');} public function sku(){return $this->belongsTo(InventorySku::class,'inventory_sku_id');} }
