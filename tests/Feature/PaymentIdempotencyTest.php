<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\BillingAutomationService;
use App\Services\PartnerCommissionService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class PaymentIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_idempotency_key_posts_payment_only_once(): void
    {
        $tenant = $this->createTenant();
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'customer_number' => 'CUST-0001',
            'name' => 'Pelanggan Test',
            'status' => 'active',
        ]);
        $invoice = Invoice::query()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-TEST-0001',
            'billing_key' => 'test:payment-idempotency',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'issued_at' => '2026-08-01',
            'due_at' => '2026-08-10',
            'subtotal' => 100000,
            'total' => 100000,
            'balance_due' => 100000,
            'status' => 'unpaid',
        ]);

        $this->mock(BillingAutomationService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('evaluateService');
        });
        $this->mock(PartnerCommissionService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('accrueForPayment')->zeroOrMoreTimes();
        });

        $key = (string) Str::uuid();
        $service = app(PaymentService::class);
        $first = $service->postToInvoice(
            $invoice,
            30000,
            'cash',
            'RECEIPT-1',
            now(),
            'Test payment',
            null,
            idempotencyKey: $key,
        );
        $second = $service->postToInvoice(
            $invoice->fresh(),
            30000,
            'cash',
            'RECEIPT-1',
            now(),
            'Retried request',
            null,
            idempotencyKey: $key,
        );

        $this->assertSame($first->id, $second->id);
        $this->assertTrue((bool) $second->getAttribute('idempotent_replay'));
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(30000, (int) $invoice->fresh()->paid_amount);
        $this->assertSame(70000, (int) $invoice->fresh()->balance_due);
    }
}
