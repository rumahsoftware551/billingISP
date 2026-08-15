<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasUlids;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['name','slug','status','timezone','currency'];
    public function users(){return $this->belongsToMany(User::class,'tenant_memberships')->withPivot(['role_id','is_default'])->withTimestamps();}
    public function subscription(){return $this->hasOne(TenantSubscription::class);}
}
