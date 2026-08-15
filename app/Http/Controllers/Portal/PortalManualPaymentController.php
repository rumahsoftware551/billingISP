<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CustomPaymentMethod;
use App\Models\Invoice;
use App\Models\ManualPaymentProof;
use App\Support\PortalContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class PortalManualPaymentController extends Controller
{
    public function store(Request $request, string $tenantSlug, Invoice $invoice): RedirectResponse
    {
        abort_unless(
            (string) $invoice->tenant_id === PortalContext::tenantId()
            && (int) $invoice->customer_id === PortalContext::customerId(),
            404
        );

        $data = $request->validate([
            'custom_payment_method_id' => ['required', 'integer'],
            'amount' => ['required', 'integer', 'min:1'],
            'payer_name' => ['nullable', 'string', 'max:160'],
            'reference' => ['nullable', 'string', 'max:160'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            'proof' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:8192'],
        ]);

        $method = CustomPaymentMethod::query()
            ->whereKey($data['custom_payment_method_id'])
            ->where('active', true)
            ->where('customer_visible', true)
            ->firstOrFail();

        $amount = (int) $data['amount'];
        $path = null;

        try {
            DB::transaction(function () use ($request, $invoice, $method, $data, $amount, &$path) {
                /** @var Invoice $lockedInvoice */
                $lockedInvoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
                abort_unless(
                    (string) $lockedInvoice->tenant_id === PortalContext::tenantId()
                    && (int) $lockedInvoice->customer_id === PortalContext::customerId(),
                    404
                );

                if ($amount > (int) $lockedInvoice->balance_due) {
                    throw ValidationException::withMessages(['amount' => 'Nominal tidak boleh melebihi sisa tagihan.']);
                }
                if ($amount < (int) $method->minimum_amount) {
                    throw ValidationException::withMessages(['amount' => 'Nominal di bawah minimum metode pembayaran ini.']);
                }
                if ($method->maximum_amount !== null && $amount > (int) $method->maximum_amount) {
                    throw ValidationException::withMessages(['amount' => 'Nominal melebihi maksimum metode pembayaran ini.']);
                }
                if (ManualPaymentProof::query()->where('invoice_id', $lockedInvoice->id)->where('status', 'pending')->exists()) {
                    throw ValidationException::withMessages(['proof' => 'Masih ada bukti pembayaran untuk invoice ini yang menunggu review.']);
                }

                $path = $request->file('proof')->store('manual-payment-proofs/'.PortalContext::tenantId(), 'local');

                ManualPaymentProof::create([
                    'invoice_id' => $lockedInvoice->id,
                    'customer_id' => $lockedInvoice->customer_id,
                    'custom_payment_method_id' => $method->id,
                    'customer_portal_account_id' => PortalContext::accountId(),
                    'amount' => $amount,
                    'payer_name' => $data['payer_name'] ?? null,
                    'reference' => $data['reference'] ?? null,
                    'proof_path' => $path,
                    'status' => 'pending',
                    'customer_note' => $data['customer_note'] ?? null,
                ]);
            }, 3);
        } catch (Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }

        return back()->with('success', 'Bukti pembayaran berhasil dikirim dan menunggu verifikasi ISP.');
    }
}
