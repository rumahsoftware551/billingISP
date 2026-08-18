<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Tenant;
use App\Services\PortalDocumentService;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommercialFinanceDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_invoice_and_receipt_routes_exist(): void
    {
        $this->assertTrue(Route::has('billing.invoices.download'));
        $this->assertTrue(Route::has('billing.payments.receipt'));
    }

    public function test_invoice_and_receipt_pdf_generators_return_pdf_documents(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Document Tenant',
            'slug' => 'document-'.Str::lower(Str::random(6)),
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));

        $customer = Customer::query()->create([
            'customer_number' => 'CUS-'.Str::upper(Str::random(6)),
            'name' => 'PDF Customer',
            'customer_type' => 'residential',
            'status' => 'active',
        ]);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-PDF-'.Str::upper(Str::random(6)),
            'billing_key' => 'pdf:'.Str::uuid(),
            'period_start' => today()->startOfMonth(),
            'period_end' => today()->endOfMonth(),
            'issued_at' => today(),
            'due_at' => today()->addDays(10),
            'subtotal' => 150000,
            'total' => 150000,
            'balance_due' => 150000,
            'status' => 'unpaid',
        ]);
        $invoice->items()->create(['description' => 'Internet', 'quantity' => 1, 'unit_price' => 150000, 'amount' => 150000]);

        $payment = Payment::query()->create([
            'customer_id' => $customer->id,
            'payment_number' => 'PAY-PDF-'.Str::upper(Str::random(6)),
            'amount' => 50000,
            'method' => 'cash',
            'paid_at' => now(),
            'status' => 'posted',
        ]);
        PaymentAllocation::query()->create(['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'amount' => 50000]);

        $documents = app(PortalDocumentService::class);
        $this->assertStringStartsWith('%PDF-1.4', $documents->invoicePdf($invoice));
        $this->assertStringStartsWith('%PDF-1.4', $documents->receiptPdf($payment));
    }
}
