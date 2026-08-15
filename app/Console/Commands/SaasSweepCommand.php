<?php
namespace App\Console\Commands;

use App\Models\PlatformEvent;
use App\Models\TenantSubscription;
use Illuminate\Console\Command;

class SaasSweepCommand extends Command
{
    protected $signature = 'jaringanku:saas-sweep';
    protected $description = 'Advance expired SaaS subscription states safely.';

    public function handle(): int
    {
        $changed = 0;
        TenantSubscription::query()->where('status','trialing')->whereNotNull('trial_ends_at')->where('trial_ends_at','<=',now())->chunkById(100, function($rows) use (&$changed){
            foreach($rows as $subscription){$subscription->update(['status'=>'suspended']);PlatformEvent::create(['tenant_id'=>$subscription->tenant_id,'event'=>'subscription.trial_expired','severity'=>'warning']);$changed++;}
        });
        TenantSubscription::query()->where('status','active')->whereNotNull('current_period_end')->where('current_period_end','<=',now())->chunkById(100, function($rows) use (&$changed){
            foreach($rows as $subscription){$subscription->update(['status'=>'past_due','grace_ends_at'=>now()->addDays(7)]);PlatformEvent::create(['tenant_id'=>$subscription->tenant_id,'event'=>'subscription.past_due','severity'=>'warning']);$changed++;}
        });
        TenantSubscription::query()->where('status','past_due')->whereNotNull('grace_ends_at')->where('grace_ends_at','<=',now())->chunkById(100, function($rows) use (&$changed){
            foreach($rows as $subscription){$subscription->update(['status'=>'suspended']);PlatformEvent::create(['tenant_id'=>$subscription->tenant_id,'event'=>'subscription.grace_expired','severity'=>'warning']);$changed++;}
        });
        $this->info("SaaS subscription sweep complete. changed={$changed}");
        return self::SUCCESS;
    }
}
