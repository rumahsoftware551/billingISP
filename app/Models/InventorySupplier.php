<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class InventorySupplier extends Model { use BelongsToTenant; protected $fillable=['tenant_id','code','name','contact_name','phone','email','address','tax_number','active','notes']; protected function casts():array{return ['active'=>'boolean'];} }
