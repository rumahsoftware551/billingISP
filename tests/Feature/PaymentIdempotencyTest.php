<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\PaymentService;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PaymentIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_retried_payment_key_creates_only_one_payment(): void
    {
        $invoice = $this->createInvoice();
        $payments = app(PaymentService::class);

        $first = $payments->postToInvoice(
            $invoice,
            50000,
            'manual:cash',
            'receipt-001',
            '2026-08-05 10:00:00+07:00',
            null,
            null,
            null,
            null,
            'manual-proof:test-0001',
        );
        $retry = $payments->postToInvoice(
            $invoice,
            50000,
            'manual:cash',
            'receipt-001',
            '2026-08-05 10:00:00+07:00',
            null,
            null,
            null,
            null,
            'manual-proof:test-0001',
        );

        $this->assertSame($first->id, $retry->id);
        $this->assertTrue((bool) $retry->getAttribute('idempotency_replayed'));
        $this->assertSame(1, Payment::count());

        $invoice->refresh();
        $this->assertSame(50000, (int) $invoice->paid_amount);
        $this->assertSame(50000, (int) $invoice->balance_due);
        $this->assertSame('partial', $invoice->status);
    }

    public function test_a_failed_after_post_callback_rolls_back_the_payment_and_invoice(): void
    {
        $invoice = $this->createInvoice();

        $thrown = false;
        try {
            app(PaymentService::class)->postToInvoice(
                $invoice,
                50000,
                'manual:cash',
                'receipt-rollback',
                '2026-08-05 10:00:00+07:00',
                null,
                null,
                null,
                null,
                'manual-proof:test-rollback',
                function (): void {
                    throw new RuntimeException('Simulated proof update failure.');
                },
            );
        } catch (RuntimeException $exception) {
            $thrown = true;
            $this->assertSame('Simulated proof update failure.', $exception->getMessage());
        }

        $this->assertTrue($thrown, 'Payment must roll back when the proof update fails.');
        $this->assertSame(0, Payment::count());
        $invoice->refresh();
        $this->assertSame(0, (int) $invoice->paid_amount);
        $this->assertSame(100000, (int) $invoice->balance_due);
        $this->assertSame('unpaid', $invoice->status);
    }

    private function createInvoice(): Invoice
    {
        $tenant = Tenant::create([
            'name' => 'Test ISP',
            'slug' => 'test-isp',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));

        $customer = Customer::create([
            'customer_number' => 'CUS-0001',
            'name' => 'Test Customer',
            'status' => 'active',
        ]);
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-TEST-0001',
            'billing_key' => 'test:invoice:0001',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'issued_at' => '2026-08-01',
            'due_at' => today()->addDay()->toDateString(),
            'subtotal' => 100000,
            'total' => 100000,
            'paid_amount' => 0,
            'balance_due' => 100000,
            'status' => 'unpaid',
        ]);

        return $invoice;
    }
}
