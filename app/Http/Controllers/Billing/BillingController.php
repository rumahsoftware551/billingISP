<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\BillingRun;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\BillingEngine;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function index(Request $request, BillingEngine $billing): Response
    {
        $tenant = app(CurrentTenant::class)->tenant;
        $billing->refreshStatuses($tenant);

        $status = trim((string) $request->string('status'));
        $search = trim((string) $request->string('q'));

        $invoices = Invoice::query()
            ->with([
                'customer:id,customer_number,name,phone',
                'service:id,service_number,pppoe_username,internet_plan_id',
                'service.plan:id,name,code',
            ])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.$search.'%';
                $query->where(function ($q) use ($like) {
                    $q->where('invoice_number', 'ilike', $like)
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'ilike', $like)->orWhere('customer_number', 'ilike', $like))
                        ->orWhereHas('service', fn ($s) => $s->where('service_number', 'ilike', $like)->orWhere('pppoe_username', 'ilike', $like));
                });
            })
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $invoicedThisMonth = (int) Invoice::whereBetween('issued_at', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->sum('total');
        $paidThisMonth = (int) Payment::where('status', 'posted')->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount');
        $outstanding = (int) Invoice::whereIn('status', ['unpaid', 'partial', 'overdue'])->sum('balance_due');
        $overdueOutstanding = (int) Invoice::where('status', 'overdue')->sum('balance_due');

        return Inertia::render('Billing/Index', [
            'invoices' => $invoices,
            'payments' => Payment::query()
                ->with(['customer:id,customer_number,name', 'allocations.invoice:id,invoice_number'])
                ->latest('paid_at')->limit(12)->get(),
            'runs' => BillingRun::query()->latest('started_at')->limit(10)->get(),
            'filters' => ['status' => $status, 'q' => $search],
            'defaultPeriod' => now()->format('Y-m'),
            'stats' => [
                'invoice_count' => Invoice::count(),
                'unpaid_count' => Invoice::whereIn('status', ['unpaid', 'partial', 'overdue'])->count(),
                'overdue_count' => Invoice::where('status', 'overdue')->count(),
                'outstanding' => $outstanding,
                'overdue_outstanding' => $overdueOutstanding,
                'partial_count' => Invoice::where('status', 'partial')->count(),
                'paid_today' => (int) Payment::where('status', 'posted')->whereBetween('paid_at', [now()->startOfDay(), now()->endOfDay()])->sum('amount'),
                'paid_this_month' => $paidThisMonth,
                'invoiced_this_month' => $invoicedThisMonth,
                'collection_rate' => $invoicedThisMonth > 0 ? round(($paidThisMonth / $invoicedThisMonth) * 100, 1) : 0.0,
            ],
        ]);
    }

    public function run(Request $request, BillingEngine $billing): RedirectResponse
    {
        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
        ]);

        $period = CarbonImmutable::createFromFormat('!Y-m', $data['period'])->startOfMonth();
        $tenant = app(CurrentTenant::class)->tenant;
        $run = $billing->runForTenant($tenant, $period, auth()->id());

        $message = sprintf(
            'Billing run %s selesai: %d invoice baru, %d sudah ada, %d error.',
            $period->format('Y-m'),
            $run->created_count,
            $run->skipped_count,
            $run->error_count
        );

        return back()->with($run->error_count > 0 ? 'error' : 'success', $message);
    }
}
