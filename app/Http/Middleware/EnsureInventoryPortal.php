<?php
namespace App\Http\Middleware;

use App\Models\InventoryPortalAccount;
use App\Models\Tenant;
use App\Services\SaasPlanService;
use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInventoryPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantSlug = (string) $request->route('tenantSlug');
        $tenant = Tenant::query()->where('slug', $tenantSlug)->where('status', 'active')->firstOrFail();
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));

        $accountId = (int) $request->session()->get('inventory_account_id', 0);
        $tenantId = (string) $request->session()->get('inventory_tenant_id', '');
        if ($accountId < 1 || $tenantId !== (string) $tenant->id) {
            $request->session()->forget(['inventory_account_id', 'inventory_tenant_id']);
            return redirect()->route('inventory.login', ['tenantSlug' => $tenantSlug]);
        }

        $account = InventoryPortalAccount::query()->with(['location', 'technician'])->whereKey($accountId)->where('status', 'active')->first();
        if (! $account) {
            $request->session()->forget(['inventory_account_id', 'inventory_tenant_id']);
            return redirect()->route('inventory.login', ['tenantSlug' => $tenantSlug]);
        }

        $summary = app(SaasPlanService::class)->summary($tenant);
        abort_unless($summary['usable'], 402, 'Subscription tenant tidak aktif.');
        $request->attributes->set('inventory_account', $account);
        $request->attributes->set('subscription_summary', $summary);
        if ($account->must_change_password && ! $request->routeIs('inventory.dashboard', 'inventory.password', 'inventory.logout')) {
            return redirect()->route('inventory.dashboard', ['tenantSlug' => $tenantSlug])->with('error', 'Ganti password sementara sebelum melakukan transaksi inventory.');
        }
        return $next($request);
    }
}
