<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\PortalDocumentService;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function show(Invoice $invoice): Response
    {
        $this->ensureTenantOwnership($invoice);
        $invoice->load([
            'customer:id,customer_number,name,email,phone',
            'service:id,service_number,pppoe_username,internet_plan_id,status',
            'service.plan:id,name,code,download_kbps,upload_kbps',
            'items',
            'allocations.payment.creator:id,name',
            'gatewayTransactions' => fn ($q) => $q->latest('id')->limit(10),
        ]);

        $gateway = \App\Models\PaymentGatewaySetting::query()->first();
        return Inertia::render('Billing/Show', [
            'invoice' => $invoice,
            'paymentMethods' => ['cash', 'transfer', 'qris', 'card', 'other'],
            'gateway' => $gateway ? [
                'enabled' => (bool) $gateway->enabled,
                'provider' => $gateway->provider,
                'environment' => $gateway->environment,
            ] : ['enabled' => false, 'provider' => null, 'environment' => null],
        ]);
    }

    public function download(Invoice $invoice, PortalDocumentService $documents): HttpResponse
    {
        $this->ensureTenantOwnership($invoice);

        return response($documents->invoicePdf($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="invoice-'.$invoice->invoice_number.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
