<?php
namespace App\Http\Middleware;

use App\Models\PartnerAccount;
use App\Models\Tenant;
use App\Services\SaasPlanService;
use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePartnerPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantSlug = (string) $request->route('tenantSlug');
        $tenant = Tenant::query()->where('slug', $tenantSlug)->where('status', 'active')->firstOrFail();
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));

        $accountId = (int) $request->session()->get('partner_account_id', 0);
        $tenantId = (string) $request->session()->get('partner_tenant_id', '');
        if ($accountId < 1 || $tenantId !== (string) $tenant->id) {
            $request->session()->forget(['partner_account_id', 'partner_tenant_id']);
            return redirect()->route('partner.login', ['tenantSlug' => $tenantSlug]);
        }

        $account = PartnerAccount::query()->with('partner')->whereKey($accountId)->where('status', 'active')->first();
        if (! $account || ! $account->partner || $account->partner->status !== 'active') {
            $request->session()->forget(['partner_account_id', 'partner_tenant_id']);
            return redirect()->route('partner.login', ['tenantSlug' => $tenantSlug]);
        }

        $summary = app(SaasPlanService::class)->summary($tenant);
        abort_unless($summary['usable'], 402, 'Subscription tenant tidak aktif.');
        $request->attributes->set('partner_account', $account);
        $request->attributes->set('subscription_summary', $summary);
        return $next($request);
    }
}
