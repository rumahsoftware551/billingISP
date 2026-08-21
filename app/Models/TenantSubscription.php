<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantSubscription extends Model
{
    protected $fillable = ['tenant_id','platform_plan_id','status','trial_ends_at','current_period_start','current_period_end','grace_ends_at','external_reference','metadata'];
    protected function casts(): array { return ['trial_ends_at'=>'datetime','current_period_start'=>'datetime','current_period_end'=>'datetime','grace_ends_at'=>'datetime','metadata'=>'array']; }
    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function plan() { return $this->belongsTo(PlatformPlan::class, 'platform_plan_id'); }
}
