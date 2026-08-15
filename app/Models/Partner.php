<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class Partner extends Model {
    use BelongsToTenant;
    protected $fillable=['tenant_id','code','name','status','email','phone','address','area_name','payout_account','notes'];
    protected function casts():array{return ['payout_account'=>'array'];}
    public function accounts(){return $this->hasMany(PartnerAccount::class);}
    public function customers(){return $this->hasMany(Customer::class);}
    public function rules(){return $this->hasMany(PartnerCommissionRule::class);}
    public function commissions(){return $this->hasMany(PartnerCommissionEntry::class);}
    public function withdrawals(){return $this->hasMany(PartnerWithdrawal::class);}
}
