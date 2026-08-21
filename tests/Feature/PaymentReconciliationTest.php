<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Tenant;
use App\Services\PaymentReconciliationService;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance(CurrentTenant::class);
        parent::tearDown();
    }

    public function test_reconciliation_repairs_invoice_derived_amounts(): void
    {
        $tenant = $this->tenant('repair');
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));
        $customer = $this->customer();
        $invoice = $this->invoice($customer, 300000);

        $payment = Payment::query()->create([
            'customer_id' => $customer->id,
            'payment_number' => 'PAY-TEST-'.Str::upper(Str::random(6)),
            'amount' => 100000,
            'method' => 'cash',
            'paid_at' => now(),
            'status' => 'posted',
        ]);
        PaymentAllocation::query()->create(['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'amount' => 100000]);

        $invoice->forceFill(['paid_amount' => 0, 'balance_due' => 300000, 'status' => 'unpaid'])->save();

        $stats = app(PaymentReconciliationService::class)->reconcileTenant($tenant, true);
        $invoice->refresh();

        $this->assertSame(1, $stats['mismatches']);
        $this->assertSame(1, $stats['repaired']);
        $this->assertSame(100000, (int) $invoice->paid_amount);
        $this->assertSame(200000, (int) $invoice->balance_due);
        $this->assertSame('partial', $invoice->status);
    }

    public function test_reconciliation_reports_overallocation_without_clamping_it(): void
    {
        $tenant = $this->tenant('violation');
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));
        $customer = $this->customer();
        $invoice = $this->invoice($customer, 100000);

        $payment = Payment::query()->create([
            'customer_id' => $customer->id,
            'payment_number' => 'PAY-TEST-'.Str::upper(Str::random(6)),
            'amount' => 120000,
            'method' => 'cash',
            'paid_at' => now(),
            'status' => 'posted',
        ]);
        PaymentAllocation::query()->create(['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'amount' => 120000]);

        $stats = app(PaymentReconciliationService::class)->reconcileTenant($tenant, true);
        $this->assertSame(1, $stats['violations']);
    }

    private function tenant(string $suffix): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Tenant '.$suffix,
            'slug' => 'tenant-'.$suffix.'-'.Str::lower(Str::random(6)),
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);
    }

    private function customer(): Customer
    {
        return Customer::query()->create([
            'customer_number' => 'CUS-'.Str::upper(Str::random(8)),
            'name' => 'Reconcile Customer',
            'customer_type' => 'residential',
            'status' => 'active',
        ]);
    }

    private function invoice(Customer $customer, int $total): Invoice
    {
        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-TEST-'.Str::upper(Str::random(8)),
            'billing_key' => 'test:'.Str::uuid(),
            'period_start' => today()->startOfMonth(),
            'period_end' => today()->endOfMonth(),
            'issued_at' => today(),
            'due_at' => today()->addDays(10),
            'subtotal' => $total,
            'discount' => 0,
            'tax' => 0,
            'total' => $total,
            'paid_amount' => 0,
            'balance_due' => $total,
            'status' => 'unpaid',
        ]);
    }
}
