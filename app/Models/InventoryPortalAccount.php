<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
class InventoryPortalAccount extends Model {
    use BelongsToTenant;
    protected $table='inventory_portal_accounts';
    protected $fillable=['tenant_id','inventory_location_id','technician_id','name','email','password','role','status','must_change_password','last_login_at','last_login_ip'];
    protected $hidden=['password'];
    protected function casts():array{return ['password'=>'hashed','must_change_password'=>'boolean','last_login_at'=>'datetime'];}
    public function location(){return $this->belongsTo(InventoryLocation::class,'inventory_location_id');}
    public function technician(){return $this->belongsTo(Technician::class);}
    public function passwordMatches(string $plain):bool{return Hash::check($plain,$this->password);}
    public function canWrite():bool{return in_array($this->role,['warehouse_manager','warehouse_staff','technician'],true);}
    public function isManager():bool{return $this->role==='warehouse_manager';}
}
