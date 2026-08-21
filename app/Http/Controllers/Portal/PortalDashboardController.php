<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CustomerService;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\PortalContext;
use Inertia\Inertia;
use Inertia\Response;

class PortalDashboardController extends Controller
{
    public function __invoke(string $tenantSlug): Response
    {
        $account = PortalContext::account()->load('customer');
        $customerId = PortalContext::customerId();

        $services = CustomerService::query()
            ->where('customer_id', $customerId)
            ->with(['plan:id,name,code,price,download_kbps,upload_kbps'])
            ->orderBy('service_number')
            ->get(['id','tenant_id','customer_id','internet_plan_id','service_number','service_type','pppoe_username','status','installed_at']);

        $invoices = Invoice::query()
            ->where('customer_id', $customerId)
            ->with(['service:id,service_number,pppoe_username'])
            ->latest('issued_at')
            ->limit(12)
            ->get();

        $payments = Payment::query()
            ->where('customer_id', $customerId)
            ->with(['allocations.invoice:id,invoice_number'])
            ->latest('paid_at')
            ->limit(10)
            ->get();

        return Inertia::render('Portal/Dashboard', [
            'portalTenantSlug' => $tenantSlug,
            'customer' => $account->customer->only('id','customer_number','name','email','phone','status'),
            'services' => $services,
            'invoices' => $invoices,
            'payments' => $payments,
            'summary' => [
                'active_services' => $services->where('status', 'active')->count(),
                'outstanding' => (int) Invoice::query()->where('customer_id', $customerId)->sum('balance_due'),
                'open_invoices' => Invoice::query()->where('customer_id', $customerId)->where('balance_due', '>', 0)->count(),
                'last_payment' => $payments->first()?->paid_at?->toIso8601String(),
            ],
        ]);
    }
}
