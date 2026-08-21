<?php

namespace App\Http\Controllers\Commercial;

use App\Http\Controllers\Controller;
use App\Models\ManualPaymentProof;
use App\Services\PaymentNotificationService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManualPaymentProofController extends Controller
{
    public function index(Request $request): Response
    {
        $status = trim((string) $request->string('status', 'pending'));
        if (! in_array($status, ['', 'pending', 'approved', 'rejected'], true)) {
            $status = 'pending';
        }

        $proofs = ManualPaymentProof::query()
            ->with([
                'invoice:id,invoice_number,total,balance_due,status',
                'customer:id,customer_number,name,phone',
                'method:id,name,type,account_name,account_number',
                'reviewer:id,name',
                'payment:id,payment_number',
            ])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Billing/ManualPayments', [
            'proofs' => $proofs,
            'filters' => ['status' => $status],
        ]);
    }

    public function review(
        Request $request,
        ManualPaymentProof $proof,
        PaymentService $payments,
        PaymentNotificationService $notifications
    ): RedirectResponse {
        $this->ensureTenantOwnership($proof);
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['action'] === 'reject') {
            DB::transaction(function () use ($proof, $data) {
                /** @var ManualPaymentProof $locked */
                $locked = ManualPaymentProof::query()->whereKey($proof->id)->lockForUpdate()->firstOrFail();
                $this->ensureTenantOwnership($locked);

                if ($locked->status !== 'pending') {
                    throw ValidationException::withMessages(['action' => 'Bukti pembayaran ini sudah direview oleh user lain.']);
                }

                $locked->forceFill([
                    'status' => 'rejected',
                    'review_note' => $data['review_note'] ?? null,
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                ])->save();
            }, 3);

            return back()->with('success', 'Bukti pembayaran ditolak.');
        }

        $proof->load(['invoice', 'method']);
        if ($proof->status !== 'pending') {
            throw ValidationException::withMessages(['action' => 'Bukti pembayaran ini sudah direview oleh user lain.']);
        }

        if (! $proof->invoice || (int) $proof->invoice->balance_due < (int) $proof->amount) {
            throw ValidationException::withMessages([
                'action' => 'Nominal bukti melebihi sisa invoice atau invoice sudah berubah. Review manual diperlukan.',
            ]);
        }

        $payment = $payments->postToInvoice(
            $proof->invoice,
            (int) $proof->amount,
            'manual:'.($proof->method?->code ?: 'custom'),
            $proof->reference,
            now(),
            'Approved from portal proof #'.$proof->id.(($data['review_note'] ?? null) ? ' · '.$data['review_note'] : ''),
            auth()->id(),
            null,
            null,
            'manual-proof:'.$proof->id,
            function ($postedPayment, $lockedInvoice) use ($proof, $data): void {
                /** @var ManualPaymentProof $locked */
                $locked = ManualPaymentProof::query()->whereKey($proof->id)->lockForUpdate()->firstOrFail();
                $this->ensureTenantOwnership($locked);

                if ($locked->status !== 'pending' || (int) $locked->invoice_id !== (int) $lockedInvoice->id) {
                    throw ValidationException::withMessages(['action' => 'Bukti pembayaran ini sudah direview atau invoice-nya berubah.']);
                }

                $locked->forceFill([
                    'status' => 'approved',
                    'review_note' => $data['review_note'] ?? null,
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                    'payment_id' => $postedPayment->id,
                ])->save();
            },
        );

        if ($payment->getAttribute('idempotency_replayed')) {
            return back()->with('error', 'Bukti pembayaran ini sudah diproses sebelumnya.');
        }

        $freshInvoice = $proof->invoice->fresh(['customer']);
        if ($freshInvoice) {
            $notifications->paymentReceived($freshInvoice, $payment);
        }

        return back()->with('success', 'Bukti disetujui dan pembayaran '.$payment->payment_number.' berhasil diposting.');
    }

    public function file(ManualPaymentProof $proof): StreamedResponse
    {
        $this->ensureTenantOwnership($proof);
        abort_unless(Storage::disk('local')->exists($proof->proof_path), 404);
        $mime = Storage::disk('local')->mimeType($proof->proof_path) ?: 'application/octet-stream';

        return Storage::disk('local')->response(
            $proof->proof_path,
            'bukti-'.$proof->id.'.'.pathinfo($proof->proof_path, PATHINFO_EXTENSION),
            ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store']
        );
    }
}
