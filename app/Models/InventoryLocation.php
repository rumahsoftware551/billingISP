<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class InventoryLocation extends Model {
    use BelongsToTenant;
    protected $fillable=['tenant_id','technician_id','code','name','location_type','address','active','metadata'];
    protected function casts():array{return ['active'=>'boolean','metadata'=>'array'];}
    public function technician(){return $this->belongsTo(Technician::class);}
    public function balances(){return $this->hasMany(InventoryBalance::class);}
}
