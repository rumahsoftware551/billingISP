<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentService
{
    public function __construct(
        private TenantSequenceService $sequences,
        private BillingEngine $billing,
        private BillingAutomationService $automation,
        private PartnerCommissionService $commissions,
    ) {}

    public function postToInvoice(
        Invoice $invoice,
        int $amount,
        string $method,
        ?string $reference,
        mixed $paidAt,
        ?string $notes,
        ?int $actorUserId,
        ?int $partnerId = null,
        ?int $partnerAccountId = null,
        ?string $idempotencyKey = null,
        ?callable $afterPost = null,
    ): Payment {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Nominal pembayaran harus lebih dari 0.']);
        }

        $idempotencyKey = filled($idempotencyKey) ? trim((string) $idempotencyKey) : null;
        if ($idempotencyKey && mb_strlen($idempotencyKey) > 120) {
            throw ValidationException::withMessages(['idempotency_key' => 'Idempotency key maksimal 120 karakter.']);
        }

        [$payment, $replayed] = DB::transaction(function () use ($invoice, $amount, $method, $reference, $paidAt, $notes, $actorUserId, $partnerId, $partnerAccountId, $idempotencyKey, $afterPost) {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, ['void'], true)) {
                throw ValidationException::withMessages(['amount' => 'Invoice void tidak dapat dibayar.']);
            }
            if ((int) $locked->balance_due <= 0) {
                throw ValidationException::withMessages(['amount' => 'Invoice ini sudah lunas.']);
            }
            if ($amount > (int) $locked->balance_due) {
                throw ValidationException::withMessages(['amount' => 'Pembayaran tidak boleh melebihi sisa tagihan.']);
            }

            $tenantId = (string) $locked->tenant_id;

            if ($idempotencyKey) {
                $existing = Payment::query()
                    ->where('tenant_id', $tenantId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $allocation = $existing->allocations()
                        ->where('invoice_id', $locked->id)
                        ->first();

                    if (! $allocation || (int) $existing->amount !== $amount || (int) $allocation->amount !== $amount || $existing->method !== $method) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => 'Idempotency key sudah digunakan untuk pembayaran yang berbeda.',
                        ]);
                    }

                    return [$existing->load(['customer', 'allocations.invoice']), true];
                }
            }

            $paidAtCarbon = $paidAt ? \Carbon\CarbonImmutable::parse($paidAt) : now()->toImmutable();
            $paymentNumber = $this->sequences->next(
                $tenantId,
                'payment:'.$paidAtCarbon->format('Ym'),
                'PAY-'.$paidAtCarbon->format('Ym').'-',
                5
            );

            $resolvedPartnerId = $partnerId ?: $locked->customer()->value('partner_id');

            $payment = Payment::create([
                'tenant_id' => $tenantId,
                'customer_id' => $locked->customer_id,
                'partner_id' => $resolvedPartnerId,
                'partner_account_id' => $partnerAccountId,
                'payment_number' => $paymentNumber,
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'idempotency_key' => $idempotencyKey,
                'paid_at' => $paidAtCarbon,
                'status' => 'posted',
                'notes' => $notes,
                'created_by' => $actorUserId,
            ]);

            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'invoice_id' => $locked->id,
                'amount' => $amount,
            ]);

            $paidAmount = (int) PaymentAllocation::query()
                ->where('invoice_id', $locked->id)
                ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
                ->where('payments.status', 'posted')
                ->sum('payment_allocations.amount');

            $balance = max(0, (int) $locked->total - $paidAmount);
            $locked->forceFill([
                'paid_amount' => $paidAmount,
                'balance_due' => $balance,
                'paid_at' => $balance === 0 ? $paidAtCarbon : null,
            ]);
            $locked->status = $this->billing->statusFor($locked);
            $locked->save();

            if ($afterPost) {
                $afterPost($payment, $locked);
            }

            return [$payment->load(['customer', 'allocations.invoice']), false];
        }, 3);

        if ($replayed) {
            $payment->setAttribute('idempotency_replayed', true);

            return $payment;
        }

        // A valid payment must remain posted even if a network-side automation action fails.
        // The scheduled automation runner will retry later.
        $freshInvoice = Invoice::query()->with('service')->find($invoice->id);
        if ($freshInvoice?->service) {
            try {
                $result = $this->automation->evaluateService(
                    $freshInvoice->service,
                    'payment',
                    $actorUserId,
                    $payment->id,
                );
                $payment->setAttribute('automation_action', $result['action']);
                $payment->setAttribute('automation_message', $result['message']);
            } catch (Throwable $e) {
                report($e);
                $payment->setAttribute('automation_action', 'error');
                $payment->setAttribute('automation_message', 'Pembayaran tersimpan, tetapi automation layanan akan dicoba ulang oleh scheduler.');
            }
        }

        try {
            $this->commissions->accrueForPayment($payment);
        } catch (Throwable $e) {
            report($e);
            $payment->setAttribute('commission_action', 'error');
        }

        return $payment;
    }
}
