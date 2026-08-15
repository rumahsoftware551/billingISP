<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PaymentGatewaySetting;
use App\Models\PaymentGatewayTransaction;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentGatewayService
{
    public function setting(): PaymentGatewaySetting
    {
        return PaymentGatewaySetting::query()->firstOrCreate([], [
            'provider' => app()->environment('local') ? 'mock' : 'midtrans',
            'environment' => 'sandbox',
            'enabled' => app()->environment('local'),
            'enabled_payments' => ['gopay', 'shopeepay', 'other_qris', 'bca_va', 'bni_va', 'bri_va', 'permata_va'],
            'expiry_minutes' => 60,
        ]);
    }

    public function initiate(Invoice $invoice, ?string $finishUrl = null, ?string $mockRedirectUrl = null): PaymentGatewayTransaction
    {
        $invoice->loadMissing(['customer', 'items']);
        if ((int) $invoice->balance_due <= 0 || in_array($invoice->status, ['paid', 'void'], true)) {
            throw ValidationException::withMessages(['gateway' => 'Invoice ini tidak memiliki saldo yang dapat dibayar.']);
        }

        $setting = $this->setting();
        if (! $setting->enabled) {
            throw ValidationException::withMessages(['gateway' => 'Payment gateway belum diaktifkan untuk tenant ini.']);
        }

        $pending = PaymentGatewayTransaction::query()
            ->where('invoice_id', $invoice->id)
            ->where('amount', (int) $invoice->balance_due)
            ->where('provider', $setting->provider)
            ->where('status', 'pending')
            ->where(function ($q) { $q->whereNull('expires_at')->orWhere('expires_at', '>', now()); })
            ->latest('id')->first();
        if ($pending) {
            if ($pending->provider === 'mock' && $mockRedirectUrl) {
                $pending->forceFill(['redirect_url' => str_replace('__TRANSACTION__', (string) $pending->id, $mockRedirectUrl)])->save();
            }
            return $pending;
        }

        $orderId = $this->newOrderId($invoice);
        $transaction = PaymentGatewayTransaction::create([
            'invoice_id' => $invoice->id,
            'provider' => $setting->provider,
            'environment' => $setting->environment,
            'order_id' => $orderId,
            'amount' => (int) $invoice->balance_due,
            'currency' => 'IDR',
            'status' => 'pending',
            'expires_at' => now()->addMinutes(max(5, (int) $setting->expiry_minutes)),
        ]);

        try {
            if ($setting->provider === 'mock') {
                abort_unless(app()->environment('local'), 403, 'Mock payment gateway hanya tersedia di local environment.');
                $response = [
                    'token' => 'mock_'.Str::random(32),
                    'redirect_url' => $mockRedirectUrl ? str_replace('__TRANSACTION__', (string) $transaction->id, $mockRedirectUrl) : url('/billing/gateway-transactions/'.$transaction->id.'/mock'),
                    'provider' => 'mock',
                ];
            } elseif ($setting->provider === 'midtrans') {
                $response = $this->createMidtransSnap($transaction, $setting, $invoice, $finishUrl);
            } else {
                throw new \RuntimeException('Payment gateway provider tidak didukung: '.$setting->provider);
            }

            if (blank($response['redirect_url'] ?? null)) {
                throw new \RuntimeException('Payment provider tidak mengembalikan redirect_url.');
            }
            if ($setting->provider === 'midtrans' && blank($response['token'] ?? null)) {
                throw new \RuntimeException('Midtrans tidak mengembalikan Snap token.');
            }

            $transaction->forceFill([
                'snap_token' => $response['token'] ?? null,
                'redirect_url' => $response['redirect_url'],
                'create_response' => $response,
            ])->save();

            return $transaction->fresh();
        } catch (\Throwable $e) {
            $transaction->forceFill(['status' => 'failed', 'create_response' => ['error' => $e->getMessage()]])->save();
            throw $e;
        }
    }

    public function checkStatus(PaymentGatewayTransaction $transaction): array
    {
        $setting = PaymentGatewaySetting::query()->withoutGlobalScopes()->where('tenant_id', $transaction->tenant_id)->firstOrFail();
        if ($transaction->provider === 'mock') {
            return ['order_id' => $transaction->order_id, 'transaction_status' => $transaction->status, 'status_code' => $transaction->status === 'paid' ? '200' : '201'];
        }
        if ($transaction->provider !== 'midtrans') {
            throw new \RuntimeException('Provider status check tidak didukung.');
        }
        $serverKey = (string) $setting->server_key;
        if ($serverKey === '') {
            throw new \RuntimeException('Midtrans Server Key belum diisi.');
        }
        $base = $setting->environment === 'production' ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
        return Http::acceptJson()->withBasicAuth($serverKey, '')->timeout(15)->retry(2, 500)
            ->get($base.'/v2/'.rawurlencode($transaction->order_id).'/status')->throw()->json();
    }

    private function createMidtransSnap(PaymentGatewayTransaction $transaction, PaymentGatewaySetting $setting, Invoice $invoice, ?string $finishUrl = null): array
    {
        $serverKey = (string) $setting->server_key;
        if ($serverKey === '') {
            throw ValidationException::withMessages(['gateway' => 'Midtrans Server Key belum diisi.']);
        }

        $endpoint = $setting->environment === 'production'
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';

        $customer = $invoice->customer;
        $name = trim((string) ($customer?->name ?: 'Pelanggan Jaringanku'));
        [$firstName, $lastName] = $this->splitName($name);
        $amount = (int) $transaction->amount;
        $payload = [
            'transaction_details' => ['order_id' => $transaction->order_id, 'gross_amount' => $amount],
            'item_details' => [[
                'id' => mb_substr((string) $invoice->invoice_number, 0, 50),
                'price' => $amount,
                'quantity' => 1,
                'name' => mb_substr('Tagihan '.$invoice->invoice_number, 0, 50),
                'category' => 'Internet Service',
            ]],
            'customer_details' => array_filter([
                'first_name' => mb_substr($firstName, 0, 50),
                'last_name' => mb_substr($lastName, 0, 50),
                'email' => $customer?->email,
                'phone' => $customer?->phone,
            ], fn ($v) => $v !== null && $v !== ''),
            'expiry' => ['unit' => 'minutes', 'duration' => max(5, (int) $setting->expiry_minutes)],
            'callbacks' => ['finish' => $finishUrl ?: url('/billing/invoices/'.$invoice->id)],
        ];
        $enabled = array_values(array_filter($setting->enabled_payments ?: []));
        if ($enabled !== []) {
            $payload['enabled_payments'] = $enabled;
        }

        return Http::acceptJson()->asJson()->withBasicAuth($serverKey, '')->timeout(20)->retry(2, 750)
            ->post($endpoint, $payload)->throw()->json();
    }

    private function newOrderId(Invoice $invoice): string
    {
        $tenant = app(CurrentTenant::class)->tenant;
        $prefix = preg_replace('/[^A-Za-z0-9_-]/', '', Str::upper(Str::limit($tenant->slug, 10, ''))) ?: 'JARINGANKU';
        return mb_substr($prefix.'-'.$invoice->invoice_number.'-'.Str::upper(Str::random(8)), 0, 50);
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [];
        return [$parts[0] ?? 'Pelanggan', $parts[1] ?? ''];
    }
}
