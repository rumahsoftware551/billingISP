<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class InventoryStockOpnameLine extends Model { use BelongsToTenant; protected $fillable=['tenant_id','inventory_stock_opname_id','inventory_sku_id','system_quantity','counted_quantity','variance_quantity','notes']; protected function casts():array{return ['system_quantity'=>'decimal:3','counted_quantity'=>'decimal:3','variance_quantity'=>'decimal:3'];} }
