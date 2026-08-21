<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class InventorySku extends Model { use BelongsToTenant; protected $fillable=['tenant_id','sku','name','category','brand','model','uom','minimum_stock','serialized','track_mac','active','metadata']; protected function casts():array{return ['minimum_stock'=>'decimal:3','serialized'=>'boolean','track_mac'=>'boolean','active'=>'boolean','metadata'=>'array'];} public function balances(){return $this->hasMany(InventoryBalance::class);} public function assets(){return $this->hasMany(InventoryItem::class,'inventory_sku_id');} }
