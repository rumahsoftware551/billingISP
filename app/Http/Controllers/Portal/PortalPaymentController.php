<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PaymentGatewayTransaction;
use App\Services\PaymentGatewayNotificationService;
use App\Services\PaymentGatewayService;
use App\Support\PortalContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PortalPaymentController extends Controller
{
    public function initiate(string $tenantSlug, Invoice $invoice, PaymentGatewayService $gateway): RedirectResponse
    {
        $this->guardInvoice($invoice);
        $transaction = $gateway->initiate(
            $invoice,
            route('portal.invoices.show', ['tenantSlug' => $tenantSlug, 'invoice' => $invoice->id]),
            route('portal.gateway.mock', ['tenantSlug' => $tenantSlug, 'transaction' => '__TRANSACTION__'])
        );
        if ($transaction->provider === 'mock' && str_contains((string) $transaction->redirect_url, '__TRANSACTION__')) {
            $transaction->forceFill(['redirect_url' => str_replace('__TRANSACTION__', (string) $transaction->id, (string) $transaction->redirect_url)])->save();
        }
        return back()->with('success', 'Link pembayaran berhasil dibuat.');
    }

    public function mock(string $tenantSlug, PaymentGatewayTransaction $transaction): Response
    {
        $this->guardTransaction($transaction);
        abort_unless(app()->environment('local') && $transaction->provider === 'mock', 404);
        $transaction->load('invoice:id,customer_id,invoice_number,total,balance_due,status');
        return Inertia::render('Portal/MockGateway', ['portalTenantSlug' => $tenantSlug, 'transaction' => $transaction]);
    }

    public function settleMock(string $tenantSlug, PaymentGatewayTransaction $transaction, PaymentGatewayNotificationService $service): RedirectResponse
    {
        $this->guardTransaction($transaction);
        $service->settleMock($transaction);
        return redirect()->route('portal.invoices.show', ['tenantSlug' => $tenantSlug, 'invoice' => $transaction->invoice_id])
            ->with('success', 'Pembayaran QRIS mock berhasil diselesaikan.');
    }

    private function guardInvoice(Invoice $invoice): void
    {
        abort_unless((string) $invoice->tenant_id === PortalContext::tenantId() && (int) $invoice->customer_id === PortalContext::customerId(), 404);
    }

    private function guardTransaction(PaymentGatewayTransaction $transaction): void
    {
        abort_unless((string) $transaction->tenant_id === PortalContext::tenantId(), 404);
        $invoice = Invoice::query()->findOrFail($transaction->invoice_id);
        $this->guardInvoice($invoice);
    }
}
