<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Router;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function __construct(private readonly CurrentTenant $currentTenant)
    {
    }

    public function dashboard(int $months = 6): array
    {
        $to = CarbonImmutable::today()->endOfDay();
        $from = $to->subMonths(max(1, $months) - 1)->startOfMonth();
        $financial = $this->monthlyFinancial($from, $to);
        $customerGrowth = $this->customerGrowth($from, $to);
        $network = $this->network($from, $to);

        $invoicedThisMonth = (int) Invoice::query()
            ->whereBetween('issued_at', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('total');
        $paidThisMonth = (int) Payment::query()
            ->where('status', 'posted')
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        return [
            'kpis' => [
                'customers' => Customer::query()->count(),
                'new_customers_month' => Customer::query()->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
                'active_services' => CustomerService::query()->where('status', 'active')->count(),
                'suspended_services' => CustomerService::query()->where('status', 'suspended')->count(),
                'online_sessions' => $network['online_sessions'],
                'routers_down' => Router::query()->where('status', '!=', 'online')->count(),
                'invoiced_month' => $invoicedThisMonth,
                'revenue_month' => $paidThisMonth,
                'outstanding' => (int) Invoice::query()->where('balance_due', '>', 0)->sum('balance_due'),
                'collection_rate' => $invoicedThisMonth > 0 ? round(($paidThisMonth / $invoicedThisMonth) * 100, 1) : 0.0,
                'traffic_period_bytes' => $network['traffic_period_bytes'],
            ],
            'financial' => $financial,
            'customer_growth' => $customerGrowth,
            'service_status' => $this->serviceStatus(),
            'aging' => $this->outstandingAging(),
            'top_outstanding' => $this->topOutstandingCustomers(5),
            'top_usage' => array_slice($network['top_usage'], 0, 5),
        ];
    }

    public function report(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => $this->summary($from, $to),
            'financial' => $this->monthlyFinancial($from, $to),
            'customer_growth' => $this->customerGrowth($from, $to),
            'service_status' => $this->serviceStatus(),
            'plan_distribution' => $this->planDistribution(),
            'aging' => $this->outstandingAging(),
            'top_outstanding' => $this->topOutstandingCustomers(10),
            'network' => $this->network($from, $to),
            'automation' => $this->automation($from, $to),
        ];
    }

    public function summary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $invoice = Invoice::query()->whereBetween('issued_at', [$from->toDateString(), $to->toDateString()]);
        $payment = Payment::query()->where('status', 'posted')->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()]);

        $invoiced = (int) (clone $invoice)->sum('total');
        $payments = (int) (clone $payment)->sum('amount');

        return [
            'customers_total' => Customer::query()->count(),
            'customers_new' => Customer::query()->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])->count(),
            'services_active' => CustomerService::query()->where('status', 'active')->count(),
            'services_suspended' => CustomerService::query()->where('status', 'suspended')->count(),
            'invoice_count' => (clone $invoice)->count(),
            'invoiced' => $invoiced,
            'payments' => $payments,
            'outstanding' => (int) Invoice::query()->where('balance_due', '>', 0)->sum('balance_due'),
            'collection_rate' => $invoiced > 0 ? round(($payments / $invoiced) * 100, 1) : 0.0,
        ];
    }

    public function monthlyFinancial(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $months = $this->monthKeys($from, $to);
        $invoiceRows = Invoice::query()
            ->whereBetween('issued_at', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("to_char(issued_at, 'YYYY-MM') as period, SUM(total)::bigint as invoiced, SUM(paid_amount)::bigint as allocated, SUM(balance_due)::bigint as balance")
            ->groupByRaw("to_char(issued_at, 'YYYY-MM')")
            ->get()->keyBy('period');
        $paymentRows = Payment::query()
            ->where('status', 'posted')
            ->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw("to_char(paid_at, 'YYYY-MM') as period, SUM(amount)::bigint as payments")
            ->groupByRaw("to_char(paid_at, 'YYYY-MM')")
            ->get()->keyBy('period');

        return array_map(function (string $period) use ($invoiceRows, $paymentRows) {
            $invoice = $invoiceRows->get($period);
            $payment = $paymentRows->get($period);
            return [
                'period' => $period,
                'invoiced' => (int) ($invoice->invoiced ?? 0),
                'allocated' => (int) ($invoice->allocated ?? 0),
                'payments' => (int) ($payment->payments ?? 0),
                'balance' => (int) ($invoice->balance ?? 0),
            ];
        }, $months);
    }

    public function customerGrowth(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $months = $this->monthKeys($from, $to);
        $rows = Customer::query()
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw("to_char(created_at, 'YYYY-MM') as period, COUNT(*)::int as total")
            ->groupByRaw("to_char(created_at, 'YYYY-MM')")
            ->pluck('total', 'period');

        return array_map(fn (string $period) => ['period' => $period, 'total' => (int) ($rows[$period] ?? 0)], $months);
    }

    public function serviceStatus(): array
    {
        return CustomerService::query()
            ->selectRaw('status, COUNT(*)::int as total')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($row) => ['status' => (string) $row->status, 'total' => (int) $row->total])
            ->all();
    }

    public function planDistribution(): array
    {
        return CustomerService::query()
            ->join('internet_plans as p', 'p.id', '=', 'customer_services.internet_plan_id')
            ->selectRaw('p.id, p.code, p.name, COUNT(customer_services.id)::int as total')
            ->groupBy('p.id', 'p.code', 'p.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['id' => (int) $row->id, 'code' => (string) $row->code, 'name' => (string) $row->name, 'total' => (int) $row->total])
            ->all();
    }

    public function outstandingAging(): array
    {
        $rows = Invoice::query()
            ->where('balance_due', '>', 0)
            ->selectRaw("CASE
                WHEN due_at >= CURRENT_DATE THEN 'current'
                WHEN CURRENT_DATE - due_at BETWEEN 1 AND 30 THEN '1_30'
                WHEN CURRENT_DATE - due_at BETWEEN 31 AND 60 THEN '31_60'
                WHEN CURRENT_DATE - due_at BETWEEN 61 AND 90 THEN '61_90'
                ELSE '90_plus' END as bucket,
                COUNT(*)::int as invoices, SUM(balance_due)::bigint as amount")
            ->groupBy('bucket')
            ->get()->keyBy('bucket');

        $labels = [
            'current' => 'Belum jatuh tempo',
            '1_30' => '1–30 hari',
            '31_60' => '31–60 hari',
            '61_90' => '61–90 hari',
            '90_plus' => '> 90 hari',
        ];

        return collect($labels)->map(function (string $label, string $key) use ($rows) {
            $row = $rows->get($key);
            return ['bucket' => $key, 'label' => $label, 'invoices' => (int) ($row->invoices ?? 0), 'amount' => (int) ($row->amount ?? 0)];
        })->values()->all();
    }

    public function topOutstandingCustomers(int $limit = 10): array
    {
        return Invoice::query()
            ->join('customers as c', 'c.id', '=', 'invoices.customer_id')
            ->where('invoices.balance_due', '>', 0)
            ->selectRaw('c.id, c.customer_number, c.name, COUNT(invoices.id)::int as invoices, SUM(invoices.balance_due)::bigint as outstanding')
            ->groupBy('c.id', 'c.customer_number', 'c.name')
            ->orderByDesc('outstanding')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'customer_number' => (string) $row->customer_number,
                'name' => (string) $row->name,
                'invoices' => (int) $row->invoices,
                'outstanding' => (int) $row->outstanding,
            ])->all();
    }

    public function network(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $tenantId = $this->currentTenant->id();
        $base = DB::table('radacct as r')
            ->join('customer_services as s', 's.pppoe_username', '=', 'r.username')
            ->where('s.tenant_id', $tenantId)
            ->whereNull('s.deleted_at');

        $onlineSessions = (clone $base)->whereNull('r.acctstoptime')->count();
        $traffic = (clone $base)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('r.acctstarttime', [$from->startOfDay(), $to->endOfDay()])
                    ->orWhereBetween('r.acctupdatetime', [$from->startOfDay(), $to->endOfDay()])
                    ->orWhereBetween('r.acctstoptime', [$from->startOfDay(), $to->endOfDay()]);
            })
            ->selectRaw('COALESCE(SUM(COALESCE(r.acctinputoctets,0) + COALESCE(r.acctoutputoctets,0)),0)::bigint as bytes')
            ->value('bytes');

        $topUsage = (clone $base)
            ->join('customers as c', 'c.id', '=', 's.customer_id')
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('r.acctstarttime', [$from->startOfDay(), $to->endOfDay()])
                    ->orWhereBetween('r.acctupdatetime', [$from->startOfDay(), $to->endOfDay()])
                    ->orWhereBetween('r.acctstoptime', [$from->startOfDay(), $to->endOfDay()]);
            })
            ->selectRaw('s.id, s.service_number, s.pppoe_username, c.customer_number, c.name, COALESCE(SUM(COALESCE(r.acctinputoctets,0) + COALESCE(r.acctoutputoctets,0)),0)::bigint as bytes')
            ->groupBy('s.id', 's.service_number', 's.pppoe_username', 'c.customer_number', 'c.name')
            ->orderByDesc('bytes')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'service_number' => (string) $row->service_number,
                'pppoe_username' => (string) $row->pppoe_username,
                'customer_number' => (string) $row->customer_number,
                'customer_name' => (string) $row->name,
                'bytes' => (int) $row->bytes,
            ])->all();

        return [
            'online_sessions' => $onlineSessions,
            'traffic_period_bytes' => (int) ($traffic ?? 0),
            'top_usage' => $topUsage,
        ];
    }

    public function automation(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $events = DB::table('automation_events')
            ->where('tenant_id', $this->currentTenant->id())
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('event, COUNT(*)::int as total, SUM(CASE WHEN success THEN 1 ELSE 0 END)::int as success_count')
            ->groupBy('event')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['event' => (string) $row->event, 'total' => (int) $row->total, 'success' => (int) $row->success_count])
            ->all();

        $recent = DB::table('automation_events as e')
            ->leftJoin('customer_services as s', 's.id', '=', 'e.customer_service_id')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->where('e.tenant_id', $this->currentTenant->id())
            ->orderByDesc('e.id')->limit(20)
            ->get(['e.id', 'e.event', 'e.success', 'e.message', 'e.created_at', 's.service_number', 's.pppoe_username', 'c.customer_number', 'c.name'])
            ->map(fn ($row) => (array) $row)->all();

        return ['events' => $events, 'recent' => $recent];
    }

    public function exportDataset(string $type, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return match ($type) {
            'customers' => $this->exportCustomers(),
            'services' => $this->exportServices(),
            'invoices' => $this->exportInvoices($from, $to, false),
            'outstanding' => $this->exportInvoices($from, $to, true),
            'payments' => $this->exportPayments($from, $to),
            'sessions' => $this->exportSessions(),
            default => throw new \InvalidArgumentException('Jenis report tidak didukung.'),
        };
    }

    private function exportCustomers(): array
    {
        $headers = ['customer_number','name','customer_type','phone','email','status','services','created_at'];
        $rows = Customer::query()->withCount('services')->orderBy('customer_number')->get()->map(fn ($c) => [
            $c->customer_number, $c->name, $c->customer_type, $c->phone, $c->email, $c->status, $c->services_count, optional($c->created_at)->toIso8601String(),
        ])->all();
        return compact('headers', 'rows');
    }

    private function exportServices(): array
    {
        $headers = ['service_number','customer_number','customer','pppoe_username','plan','status','static_ip','billing_day','due_day','installed_at'];
        $rows = CustomerService::query()->with(['customer:id,customer_number,name', 'plan:id,name'])->orderBy('service_number')->get()->map(fn ($s) => [
            $s->service_number, $s->customer?->customer_number, $s->customer?->name, $s->pppoe_username, $s->plan?->name, $s->status, $s->static_ip, $s->billing_day, $s->due_day, optional($s->installed_at)->toIso8601String(),
        ])->all();
        return compact('headers', 'rows');
    }

    private function exportInvoices(CarbonImmutable $from, CarbonImmutable $to, bool $outstanding): array
    {
        $headers = ['invoice_number','customer_number','customer','service_number','period_start','period_end','due_at','total','paid_amount','balance_due','status'];
        $query = Invoice::query()->with(['customer:id,customer_number,name','service:id,service_number']);
        if ($outstanding) {
            $query->where('balance_due', '>', 0);
        } else {
            $query->whereBetween('issued_at', [$from->toDateString(), $to->toDateString()]);
        }
        $rows = $query->orderBy('issued_at')->orderBy('id')->get()->map(fn ($i) => [
            $i->invoice_number, $i->customer?->customer_number, $i->customer?->name, $i->service?->service_number, optional($i->period_start)->toDateString(), optional($i->period_end)->toDateString(), optional($i->due_at)->toDateString(), $i->total, $i->paid_amount, $i->balance_due, $i->status,
        ])->all();
        return compact('headers', 'rows');
    }

    private function exportPayments(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $headers = ['payment_number','customer_number','customer','amount','method','reference','paid_at','status'];
        $rows = Payment::query()->with('customer:id,customer_number,name')->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()])->orderBy('paid_at')->get()->map(fn ($p) => [
            $p->payment_number, $p->customer?->customer_number, $p->customer?->name, $p->amount, $p->method, $p->reference, optional($p->paid_at)->toIso8601String(), $p->status,
        ])->all();
        return compact('headers', 'rows');
    }

    private function exportSessions(): array
    {
        $headers = ['service_number','customer_number','customer','username','framed_ip','nas_ip','start_time','last_update','input_octets','output_octets'];
        $rows = DB::table('radacct as r')
            ->join('customer_services as s', 's.pppoe_username', '=', 'r.username')
            ->join('customers as c', 'c.id', '=', 's.customer_id')
            ->where('s.tenant_id', $this->currentTenant->id())
            ->whereNull('s.deleted_at')->whereNull('r.acctstoptime')
            ->orderByDesc('r.acctstarttime')
            ->get(['s.service_number','c.customer_number','c.name','r.username','r.framedipaddress','r.nasipaddress','r.acctstarttime','r.acctupdatetime','r.acctinputoctets','r.acctoutputoctets'])
            ->map(fn ($r) => [(string)$r->service_number,(string)$r->customer_number,(string)$r->name,(string)$r->username,(string)$r->framedipaddress,(string)$r->nasipaddress,(string)$r->acctstarttime,(string)$r->acctupdatetime,(int)$r->acctinputoctets,(int)$r->acctoutputoctets])->all();
        return compact('headers', 'rows');
    }

    private function monthKeys(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $cursor = $from->startOfMonth();
        $end = $to->startOfMonth();
        $months = [];
        while ($cursor->lessThanOrEqualTo($end)) {
            $months[] = $cursor->format('Y-m');
            $cursor = $cursor->addMonth();
        }
        return $months;
    }
}
