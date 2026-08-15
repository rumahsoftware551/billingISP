<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class InventoryTransactionLine extends Model { use BelongsToTenant; protected $fillable=['tenant_id','inventory_transaction_id','inventory_sku_id','inventory_item_id','quantity','unit_cost','notes']; protected function casts():array{return ['quantity'=>'decimal:3','unit_cost'=>'decimal:2'];} public function sku(){return $this->belongsTo(InventorySku::class,'inventory_sku_id');} public function asset(){return $this->belongsTo(InventoryItem::class,'inventory_item_id');} }
