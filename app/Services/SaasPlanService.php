<?php
namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaasPlanService
{
    private const RESOURCE_MAP = [
        'customers' => ['table'=>'customers','limit'=>'max_customers','soft_delete'=>true],
        'services' => ['table'=>'customer_services','limit'=>'max_services','soft_delete'=>true],
        'routers' => ['table'=>'routers','limit'=>'max_routers'],
        'users' => ['table'=>'tenant_memberships','limit'=>'max_users'],
    ];

    public function subscription(Tenant|string $tenant): ?TenantSubscription
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        return TenantSubscription::query()->with('plan')->where('tenant_id', $tenantId)->first();
    }

    public function usable(TenantSubscription $subscription): bool
    {
        if (in_array($subscription->status, ['suspended','canceled'], true)) return false;
        if ($subscription->status === 'trialing') return ! $subscription->trial_ends_at || $subscription->trial_ends_at->isFuture();
        if ($subscription->status === 'past_due') return (bool) ($subscription->grace_ends_at && $subscription->grace_ends_at->isFuture());
        if ($subscription->status !== 'active') return false;
        if ($subscription->current_period_end && $subscription->current_period_end->isPast()) return (bool) ($subscription->grace_ends_at && $subscription->grace_ends_at->isFuture());
        return true;
    }

    public function usage(Tenant|string $tenant): array
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        return [
            'customers' => DB::table('customers')->where('tenant_id', $tenantId)->whereNull('deleted_at')->count(),
            'services' => DB::table('customer_services')->where('tenant_id', $tenantId)->whereNull('deleted_at')->count(),
            'routers' => DB::table('routers')->where('tenant_id', $tenantId)->count(),
            'users' => DB::table('tenant_memberships')->where('tenant_id', $tenantId)->count(),
        ];
    }

    public function summary(Tenant|string $tenant): array
    {
        $subscription = $this->subscription($tenant);
        if (! $subscription) return ['status'=>'missing','usable'=>false,'plan'=>null,'usage'=>$this->usage($tenant),'limits'=>[]];
        $limits = collect(self::RESOURCE_MAP)->mapWithKeys(fn($m,$resource)=>[$resource=>$subscription->plan->{$m['limit']}])->all();
        return [
            'status'=>$subscription->status,
            'usable'=>$this->usable($subscription),
            'plan'=>$subscription->plan?->only('id','code','name','monthly_price'),
            'usage'=>$this->usage($tenant),
            'limits'=>$limits,
            'trial_ends_at'=>$subscription->trial_ends_at?->toIso8601String(),
            'current_period_end'=>$subscription->current_period_end?->toIso8601String(),
            'grace_ends_at'=>$subscription->grace_ends_at?->toIso8601String(),
        ];
    }

    public function assertCanCreate(Tenant|string $tenant, string $resource): void
    {
        $subscription = $this->subscription($tenant);
        if (! $subscription || ! $this->usable($subscription)) {
            throw ValidationException::withMessages(['subscription'=>'Subscription tenant tidak aktif.']);
        }
        $map = self::RESOURCE_MAP[$resource] ?? null;
        if (! $map) throw ValidationException::withMessages(['subscription'=>'Resource limit tidak dikenal.']);
        $limit = $subscription->plan->{$map['limit']};
        if ($limit === null) return;
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        $query = DB::table($map['table'])->where('tenant_id', $tenantId);
        if (($map['soft_delete'] ?? false) === true) $query->whereNull('deleted_at');
        $current = $query->count();
        if ($current >= $limit) {
            throw ValidationException::withMessages(['subscription'=>"Batas {$resource} paket {$subscription->plan->name} sudah tercapai ({$current}/{$limit})."]);
        }
    }
}
