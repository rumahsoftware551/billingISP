<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentGatewayEvent;
use App\Models\PaymentGatewaySetting;
use App\Models\PaymentGatewayTransaction;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\Cache;

class PaymentGatewayNotificationService
{
    public function __construct(private PaymentService $payments, private PaymentNotificationService $notifications) {}

    public function handleMidtrans(array $payload, string $rawBody): array
    {
        $hash = hash('sha256', $rawBody);
        $orderId = (string) ($payload['order_id'] ?? '');
        $transaction = PaymentGatewayTransaction::query()->withoutGlobalScopes()->where('order_id', $orderId)->first();
        // firstOrCreate uses the unique event_hash as a concurrency barrier for duplicate webhook deliveries.
        $event = PaymentGatewayEvent::query()->firstOrCreate(
            ['event_hash' => $hash],
            [
                'tenant_id' => $transaction?->tenant_id,
                'provider' => 'midtrans',
                'order_id' => $orderId ?: null,
                'provider_transaction_id' => $payload['transaction_id'] ?? null,
                'payload' => $payload,
                'signature_valid' => false,
                'status' => 'received',
            ]
        );
        if (! $event->wasRecentlyCreated && $event->status === 'processed') {
            return ['ok' => true, 'duplicate' => true, 'status' => 'processed'];
        }

        if (! $transaction) {
            $event->update(['status' => 'ignored', 'error' => 'Unknown order_id', 'processed_at' => now()]);
            return ['ok' => false, 'http' => 404, 'message' => 'Unknown order_id'];
        }

        $tenant = Tenant::query()->findOrFail($transaction->tenant_id);
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));
        $setting = PaymentGatewaySetting::query()->first();
        $serverKey = (string) $setting?->server_key;
        $signature = (string) ($payload['signature_key'] ?? '');
        $expected = hash('sha512', $orderId.(string)($payload['status_code'] ?? '').(string)($payload['gross_amount'] ?? '').$serverKey);
        $valid = $serverKey !== '' && $signature !== '' && hash_equals($expected, $signature);
        $event->forceFill(['tenant_id' => $tenant->id, 'signature_valid' => $valid])->save();
        if (! $valid) {
            $event->update(['status' => 'rejected', 'error' => 'Invalid signature', 'processed_at' => now()]);
            return ['ok' => false, 'http' => 401, 'message' => 'Invalid signature'];
        }

        return Cache::lock('jaringanku:gateway:'.$transaction->id, 15)->block(5, function () use ($transaction, $event, $payload) {
            $transaction->refresh();
            $gross = (int) round((float) ($payload['gross_amount'] ?? 0));
            if ($gross !== (int) $transaction->amount) {
                $event->forceFill(['status' => 'rejected', 'error' => 'Gross amount mismatch', 'processed_at' => now()])->save();
                return ['ok' => false, 'http' => 422, 'message' => 'Gross amount mismatch'];
            }
            $mapped = $this->mapMidtransStatus($payload);
            if ($transaction->status === 'paid' || $transaction->payment_id) {
                $mapped = 'paid';
            } elseif (in_array($transaction->status, ['expired','cancelled','failed'], true) && $mapped === 'pending') {
                $mapped = $transaction->status;
            }
            $updates = [
                'status' => $mapped,
                'provider_transaction_id' => $payload['transaction_id'] ?? $transaction->provider_transaction_id,
                'payment_type' => $payload['payment_type'] ?? $transaction->payment_type,
                'status_response' => $payload,
                'verified_at' => now(),
            ];

            if ($mapped === 'paid' && ! $transaction->payment_id) {
                $invoice = $transaction->invoice()->firstOrFail();
                // Recovery path: if a prior callback posted the payment but failed before linking
                // the gateway row, reuse that posted payment instead of creating another one.
                $payment = Payment::query()
                    ->where('customer_id', $invoice->customer_id)
                    ->where('reference', $transaction->order_id)
                    ->where('status', 'posted')
                    ->latest('id')
                    ->first();

                if (! $payment) {
                    $amount = min((int) $transaction->amount, (int) $invoice->balance_due);
                    if ($amount > 0) {
                        $payment = $this->payments->postToInvoice(
                            $invoice,
                            $amount,
                            $payload['payment_type'] ?? 'qris',
                            $transaction->order_id,
                            now(),
                            'Payment gateway Midtrans: '.$transaction->order_id,
                            null,
                        );
                        $this->notifications->paymentReceived($invoice->fresh(['customer']), $payment);
                    }
                }

                if ($payment) {
                    $updates['payment_id'] = $payment->id;
                    $updates['paid_at'] = $payment->paid_at ?: now();
                }
            }

            $transaction->forceFill($updates)->save();
            $event->forceFill(['status' => 'processed', 'processed_at' => now(), 'error' => null])->save();
            return ['ok' => true, 'duplicate' => false, 'status' => $mapped];
        });
    }

    public function settleMock(PaymentGatewayTransaction $transaction): PaymentGatewayTransaction
    {
        abort_unless(app()->environment('local') && $transaction->provider === 'mock', 404);
        return Cache::lock('jaringanku:gateway:'.$transaction->id, 15)->block(5, function () use ($transaction) {
            $transaction->refresh();
            if ($transaction->status === 'paid') {
                return $transaction;
            }
            $invoice = $transaction->invoice()->firstOrFail();
            $payment = Payment::query()
                ->where('customer_id', $invoice->customer_id)
                ->where('reference', $transaction->order_id)
                ->where('status', 'posted')
                ->latest('id')
                ->first();
            if (! $payment) {
                $amount = min((int) $transaction->amount, (int) $invoice->balance_due);
                if ($amount > 0) {
                    $payment = $this->payments->postToInvoice($invoice, $amount, 'qris', $transaction->order_id, now(), 'Mock gateway payment Phase 09.', null);
                    $this->notifications->paymentReceived($invoice->fresh(['customer']), $payment);
                }
            }
            $transaction->forceFill([
                'status' => 'paid',
                'payment_id' => $payment?->id,
                'provider_transaction_id' => 'MOCK-'.strtoupper(substr(hash('sha256', $transaction->order_id), 0, 16)),
                'payment_type' => 'qris',
                'paid_at' => now(),
                'verified_at' => now(),
                'status_response' => ['transaction_status' => 'settlement', 'status_code' => '200', 'mock' => true],
            ])->save();
            return $transaction->fresh();
        });
    }

    private function mapMidtransStatus(array $payload): string
    {
        $status = strtolower((string) ($payload['transaction_status'] ?? ''));
        $fraud = strtolower((string) ($payload['fraud_status'] ?? 'accept'));
        $code = (string) ($payload['status_code'] ?? '');
        if ($code === '200' && in_array($status, ['settlement', 'capture'], true) && $fraud === 'accept') return 'paid';
        if (in_array($status, ['expire'], true)) return 'expired';
        if (in_array($status, ['cancel'], true)) return 'cancelled';
        if (in_array($status, ['deny', 'failure'], true)) return 'failed';
        return 'pending';
    }
}
