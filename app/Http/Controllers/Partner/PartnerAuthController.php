<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPlan;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Tenant;
use App\Services\BillingEngine;
use App\Services\PaymentService;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BillingPaymentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance(CurrentTenant::class);

        parent::tearDown();
    }

    public function test_billing_generation_is_idempotent_for_same_service_period(): void
    {
        $tenant = $this->createTenant('billing');
        $this->bindTenant($tenant);

        $customer = $this->createCustomer('BILL');

        $plan = InternetPlan::query()->create([
            'name' => 'Internet 50 Mbps',
            'code' => '50M-'.Str::upper(Str::random(4)),
            'price' => 250000,
            'download_kbps' => 50000,
            'upload_kbps' => 25000,
            'active' => true,
        ]);

        $service = CustomerService::query()->create([
            'customer_id' => $customer->id,
            'internet_plan_id' => $plan->id,
            'service_number' => 'SRV-'.Str::upper(Str::random(8)),
            'service_type' => 'pppoe',
            'pppoe_username' => 'test-'.Str::lower(Str::random(8)),
            'pppoe_password' => 'Test@12345',
            'status' => 'active',
            'billing_day' => 1,
            'due_day' => 10,
            'installed_at' => now()->subMonth(),
        ]);

        $period = CarbonImmutable::parse('2026-08-01');

        $first = app(BillingEngine::class)
            ->generateForService($service, $period);

        $second = app(BillingEngine::class)
            ->generateForService($service, $period);

        $this->assertSame($first->id, $second->id);

        $this->assertSame(
            1,
            Invoice::query()
                ->where('customer_service_id', $service->id)
                ->where('period_start', '2026-08-01')
                ->count()
        );

        $invoice = $first->fresh(['items']);

        $this->assertSame(250000, (int) $invoice->total);
        $this->assertSame(250000, (int) $invoice->balance_due);
        $this->assertSame(0, (int) $invoice->paid_amount);
        $this->assertSame('unpaid', $invoice->status);

        $this->assertCount(1, $invoice->items);

        $this->assertSame(
            'service:'.$service->id.':2026-08',
            $invoice->billing_key
        );
    }

    public function test_partial_payment_updates_invoice_correctly(): void
    {
        $tenant = $this->createTenant('partial');
        $this->bindTenant($tenant);

        $customer = $this->createCustomer('PART');

        $invoice = $this->createInvoice(
            customer: $customer,
            total: 300000
        );

        $payment = app(PaymentService::class)->postToInvoice(
            invoice: $invoice,
            amount: 100000,
            method: 'cash',
            reference: 'TEST-PARTIAL',
            paidAt: now(),
            notes: 'Automated test partial payment',
            actorUserId: null
        );

        $invoice->refresh();

        $this->assertSame('posted', $payment->status);
        $this->assertSame(100000, (int) $payment->amount);

        $this->assertSame(100000, (int) $invoice->paid_amount);
        $this->assertSame(200000, (int) $invoice->balance_due);
        $this->assertSame('partial', $invoice->status);
        $this->assertNull($invoice->paid_at);

        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => 100000,
        ]);
    }

    public function test_full_payment_marks_invoice_as_paid(): void
    {
        $tenant = $this->createTenant('full');
        $this->bindTenant($tenant);

        $customer = $this->createCustomer('FULL');

        $invoice = $this->createInvoice(
            customer: $customer,
            total: 275000
        );

        $payment = app(PaymentService::class)->postToInvoice(
            invoice: $invoice,
            amount: 275000,
            method: 'bank_transfer',
            reference: 'TEST-FULL',
            paidAt: now(),
            notes: 'Automated test full payment',
            actorUserId: null
        );

        $invoice->refresh();

        $this->assertSame('posted', $payment->status);

        $this->assertSame(275000, (int) $invoice->paid_amount);
        $this->assertSame(0, (int) $invoice->balance_due);
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);

        $this->assertSame(
            1,
            PaymentAllocation::query()
                ->where('invoice_id', $invoice->id)
                ->count()
        );
    }

    public function test_overpayment_is_rejected_without_creating_payment(): void
    {
        $tenant = $this->createTenant('overpay');
        $this->bindTenant($tenant);

        $customer = $this->createCustomer('OVER');

        $invoice = $this->createInvoice(
            customer: $customer,
            total: 200000
        );

        try {
            app(PaymentService::class)->postToInvoice(
                invoice: $invoice,
                amount: 250000,
                method: 'cash',
                reference: 'TEST-OVERPAY',
                paidAt: now(),
                notes: null,
                actorUserId: null
            );

            $this->fail(
                'Overpayment seharusnya ditolak.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'amount',
                $exception->errors()
            );
        }

        $invoice->refresh();

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, PaymentAllocation::query()->count());

        $this->assertSame(0, (int) $invoice->paid_amount);
        $this->assertSame(200000, (int) $invoice->balance_due);
        $this->assertSame('unpaid', $invoice->status);
    }

    private function createTenant(string $suffix): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Billing Tenant '.Str::upper($suffix),
            'slug' => 'billing-'.$suffix.'-'.Str::lower(Str::random(6)),
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);
    }

    private function createCustomer(string $prefix): Customer
    {
        return Customer::query()->create([
            'customer_number' => $prefix.'-'.Str::upper(Str::random(8)),
            'name' => 'Customer '.$prefix,
            'customer_type' => 'residential',
            'status' => 'active',
        ]);
    }

    private function createInvoice(
        Customer $customer,
        int $total
    ): Invoice {
        $number = 'INV-TEST-'.Str::upper(Str::random(8));

        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'customer_service_id' => null,
            'invoice_number' => $number,
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

    private function bindTenant(Tenant $tenant): void
    {
        app()->instance(
            CurrentTenant::class,
            new CurrentTenant($tenant)
        );
    }
}