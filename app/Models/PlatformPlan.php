<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformPlan extends Model
{
    protected $fillable = ['code','name','monthly_price','max_customers','max_services','max_routers','max_users','features','active'];
    protected function casts(): array { return ['features'=>'array','active'=>'boolean']; }
    public function subscriptions() { return $this->hasMany(TenantSubscription::class); }
}
