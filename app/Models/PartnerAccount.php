<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
class PartnerAccount extends Model {
    use BelongsToTenant;
    protected $fillable=['tenant_id','partner_id','name','email','password','role','status','must_change_password','last_login_at','last_login_ip'];
    protected $hidden=['password'];
    protected function casts():array{return ['must_change_password'=>'boolean','last_login_at'=>'datetime','password'=>'hashed'];}
    public function partner(){return $this->belongsTo(Partner::class);}
    public function passwordMatches(string $plain):bool{return Hash::check($plain,$this->password);}
}
