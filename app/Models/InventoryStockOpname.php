<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class InventoryStockOpname extends Model { use BelongsToTenant; protected $fillable=['tenant_id','opname_number','inventory_location_id','created_by_inventory_account_id','status','counted_at','notes']; protected function casts():array{return ['counted_at'=>'datetime'];} public function lines(){return $this->hasMany(InventoryStockOpnameLine::class);} }
