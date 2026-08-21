<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\PaymentService;
use App\Services\PaymentNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function store(Request $request, Invoice $invoice, PaymentService $payments, PaymentNotificationService $notifications): RedirectResponse
    {
        $this->ensureTenantOwnership($invoice);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::in(['cash', 'transfer', 'qris', 'card', 'other'])],
            'reference' => ['nullable', 'string', 'max:160'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $payment = $payments->postToInvoice(
            $invoice,
            (int) $data['amount'],
            $data['method'],
            $data['reference'] ?? null,
            $data['paid_at'] ?? now(),
            $data['notes'] ?? null,
            auth()->id(),
        );

        $notifications->paymentReceived($invoice->fresh(['customer']), $payment);

        $message = sprintf(
            'Pembayaran %s sebesar Rp%s berhasil diposting.',
            $payment->payment_number,
            number_format($payment->amount, 0, ',', '.')
        );

        $automationAction = $payment->getAttribute('automation_action');
        if ($automationAction === 'reactivated') {
            $message .= ' Layanan pelanggan otomatis diaktifkan kembali dan RADIUS dipulihkan.';
        } elseif ($automationAction === 'error') {
            $message .= ' Pembayaran aman tersimpan; reaktivasi otomatis akan dicoba ulang scheduler.';
        }

        return back()->with('success', $message);
    }
}
