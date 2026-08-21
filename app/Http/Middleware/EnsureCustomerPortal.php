<?php

namespace App\Http\Middleware;

use App\Models\CustomerPortalAccount;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use App\Services\SaasPlanService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $accountId = (int) $request->session()->get('portal_account_id', 0);
        $tenantId = (string) $request->session()->get('portal_tenant_id', '');
        $tenantSlug = (string) $request->route('tenantSlug', '');

        if ($accountId < 1 || $tenantId === '') {
            return redirect()->route('portal.login', ['tenantSlug' => $tenantSlug]);
        }

        $account = CustomerPortalAccount::query()
            ->with('customer')
            ->whereKey($accountId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();

        if (! $account || ! $account->customer || $account->customer->trashed()) {
            $request->session()->forget(['portal_account_id', 'portal_tenant_id']);
            return redirect()->route('portal.login', ['tenantSlug' => $tenantSlug]);
        }

        $tenant = Tenant::query()->whereKey($tenantId)->where('status', 'active')->first();
        if (! $tenant || ! hash_equals((string) $tenant->slug, $tenantSlug)) {
            abort(404);
        }

        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));
        $subscription = app(SaasPlanService::class)->summary($tenant);
        abort_unless($subscription['usable'], 402, 'Portal pelanggan sementara tidak tersedia karena subscription ISP tidak aktif.');
        $request->attributes->set('subscription_summary', $subscription);
        app()->instance(CustomerPortalAccount::class, $account);
        $request->attributes->set('portal_account', $account);

        if ($account->must_change_password && ! in_array((string) $request->route()?->getName(), ['portal.profile', 'portal.profile.password', 'portal.logout'], true)) {
            return redirect()->route('portal.profile', ['tenantSlug' => $tenantSlug]);
        }

        return $next($request);
    }
}
