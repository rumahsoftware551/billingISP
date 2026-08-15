<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CustomPaymentMethod;
use App\Models\Invoice;
use App\Models\ManualPaymentProof;
use App\Models\PaymentGatewaySetting;
use App\Services\PortalDocumentService;
use App\Support\PortalContext;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PortalInvoiceController extends Controller
{
    public function show(string $tenantSlug, Invoice $invoice): Response
    {
        $this->guardInvoice($invoice);
        $invoice->load([
            'service:id,service_number,pppoe_username,internet_plan_id,status',
            'service.plan:id,name,code,download_kbps,upload_kbps',
            'items',
            'allocations.payment:id,payment_number,amount,method,reference,paid_at,status',
            'gatewayTransactions' => fn ($q) => $q->latest('id')->limit(10),
        ]);
        $gateway = PaymentGatewaySetting::query()->first();
        $methods = CustomPaymentMethod::query()->where('active',true)->where('customer_visible',true)->orderBy('sort_order')->orderBy('name')->get()->map(function($m){
            return [...$m->toArray(),'qr_image_url'=>$m->qr_image_path ? Storage::disk('public')->url($m->qr_image_path) : null];
        });
        $proofs = ManualPaymentProof::query()->where('invoice_id',$invoice->id)->with('method:id,name,type')->latest()->get()->map(function($p){
            return $p->only('id','custom_payment_method_id','amount','payer_name','reference','status','customer_note','review_note','created_at','reviewed_at') + ['method'=>$p->method?->only('id','name','type')];
        });
        return Inertia::render('Portal/InvoiceShow', [
            'portalTenantSlug' => $tenantSlug,
            'invoice' => $invoice,
            'gateway' => $gateway ? ['enabled' => (bool) $gateway->enabled, 'provider' => $gateway->provider, 'environment' => $gateway->environment] : ['enabled' => false],
            'customPaymentMethods' => $methods,
            'manualPaymentProofs' => $proofs,
        ]);
    }

    public function download(string $tenantSlug, Invoice $invoice, PortalDocumentService $documents): HttpResponse
    {
        $this->guardInvoice($invoice);
        return response($documents->invoicePdf($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoice->invoice_number.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function guardInvoice(Invoice $invoice): void
    {
        abort_unless((string) $invoice->tenant_id === PortalContext::tenantId() && (int) $invoice->customer_id === PortalContext::customerId(), 404);
    }
}
