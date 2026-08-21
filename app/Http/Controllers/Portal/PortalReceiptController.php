<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PortalDocumentService;
use App\Support\PortalContext;
use Symfony\Component\HttpFoundation\Response;

class PortalReceiptController extends Controller
{
    public function download(string $tenantSlug, Payment $payment, PortalDocumentService $documents): Response
    {
        abort_unless((string) $payment->tenant_id === PortalContext::tenantId() && (int) $payment->customer_id === PortalContext::customerId(), 404);
        return response($documents->receiptPdf($payment), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$payment->payment_number.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
