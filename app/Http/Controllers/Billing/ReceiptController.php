<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PortalDocumentService;
use Illuminate\Http\Response;

class ReceiptController extends Controller
{
    public function download(Payment $payment, PortalDocumentService $documents): Response
    {
        $this->ensureTenantOwnership($payment);

        return response($documents->receiptPdf($payment), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="receipt-'.$payment->payment_number.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
