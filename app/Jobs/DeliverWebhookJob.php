<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\WebhookDelivery;
use App\Support\CurrentTenant;
use App\Services\WebhookUrlPolicy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class DeliverWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 45;
    public int $tries = 5;

    public function __construct(public int $deliveryId) {}

    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function handle(WebhookUrlPolicy $urlPolicy): void
    {
        $delivery = WebhookDelivery::query()->withoutGlobalScopes()->findOrFail($this->deliveryId);
        if ($delivery->status === 'delivered') {
            return;
        }

        $tenant = Tenant::query()->findOrFail($delivery->tenant_id);
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));
        $delivery->load('endpoint');
        $endpoint = $delivery->endpoint;

        if (! $endpoint || ! $endpoint->enabled) {
            $delivery->forceFill(['status' => 'cancelled', 'last_error' => 'Webhook endpoint disabled or missing.'])->save();
            return;
        }

        $urlPolicy->validateOrFail((string) $endpoint->url);

        $attempt = (int) $delivery->attempts + 1;
        $delivery->forceFill(['status' => 'sending', 'attempts' => $attempt, 'last_error' => null])->save();

        $json = json_encode($delivery->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->getTimestamp();
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$json, (string) $endpoint->secret);

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->withoutRedirecting()
                ->timeout(max(1, min(30, (int) $endpoint->timeout_seconds)))
                ->withUserAgent((string) config('jaringanku.webhook_user_agent'))
                ->withHeaders(array_filter([
                    'X-Jaringanku-Event' => $delivery->event,
                    'X-Jaringanku-Event-Id' => $delivery->event_id,
                    'X-Jaringanku-Timestamp' => $timestamp,
                    'X-Jaringanku-Signature' => $signature,
                    'X-Phase08-Smoke-Token' => app()->environment('local') ? config('jaringanku.phase08_smoke_token') : null,
                ], fn ($value) => $value !== null && $value !== ''))
                ->withBody($json, 'application/json')
                ->send('POST', (string) $endpoint->url);
        } catch (Throwable $e) {
            $delivery->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 4000)])->save();
            if ($attempt >= max(1, (int) $endpoint->max_attempts)) {
                $delivery->forceFill(['status' => 'failed'])->save();
                return;
            }
            throw $e;
        }

        $limit = max(256, (int) config('jaringanku.webhook_response_body_limit', 2048));
        $body = mb_substr((string) $response->body(), 0, $limit);

        $delivery->forceFill([
            'response_code' => $response->status(),
            'response_body' => $body,
        ])->save();

        if (! $response->successful()) {
            if ($attempt >= max(1, (int) $endpoint->max_attempts)) {
                $delivery->forceFill(['status' => 'failed', 'last_error' => 'HTTP '.$response->status()])->save();
                return;
            }
            throw new RuntimeException('Webhook HTTP '.$response->status());
        }

        $delivery->forceFill(['status' => 'delivered', 'delivered_at' => now(), 'last_error' => null])->save();
    }

    public function failed(Throwable $e): void
    {
        WebhookDelivery::query()->withoutGlobalScopes()->whereKey($this->deliveryId)->update([
            'status' => 'failed',
            'last_error' => mb_substr($e->getMessage(), 0, 4000),
            'updated_at' => now(),
        ]);
    }
}
