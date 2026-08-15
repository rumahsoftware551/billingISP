<?php

namespace App\Services;

use App\Jobs\DeliverWebhookJob;
use App\Models\Tenant;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Str;

class WebhookService
{
    /** @return array<int, WebhookDelivery> */
    public function emit(Tenant $tenant, string $event, array $payload): array
    {
        $eventId = (string) Str::uuid();
        $deliveries = [];

        $endpoints = WebhookEndpoint::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('enabled', true)
            ->get();

        foreach ($endpoints as $endpoint) {
            $events = $endpoint->events ?: ['*'];
            if (! in_array('*', $events, true) && ! in_array($event, $events, true)) {
                continue;
            }
            $deliveries[] = $this->createDelivery($tenant, $endpoint, $eventId, $event, $payload);
        }

        return $deliveries;
    }

    public function emitToEndpoint(Tenant $tenant, WebhookEndpoint $endpoint, string $event, array $payload): WebhookDelivery
    {
        abort_unless((string) $endpoint->tenant_id === (string) $tenant->id, 404);
        return $this->createDelivery($tenant, $endpoint, (string) Str::uuid(), $event, $payload);
    }

    private function createDelivery(Tenant $tenant, WebhookEndpoint $endpoint, string $eventId, string $event, array $payload): WebhookDelivery
    {
        $delivery = WebhookDelivery::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'webhook_endpoint_id' => $endpoint->id,
            'event_id' => $eventId,
            'event' => $event,
            'payload' => [
                'id' => $eventId,
                'event' => $event,
                'created_at' => now()->toIso8601String(),
                'tenant' => ['id' => $tenant->id, 'slug' => $tenant->slug],
                'data' => $payload,
            ],
            'status' => 'pending',
        ]);

        $delivery->forceFill(['status' => 'queued'])->save();
        try {
            DeliverWebhookJob::dispatch($delivery->id);
        } catch (\Throwable $e) {
            $delivery->forceFill(['status' => 'failed', 'last_error' => mb_substr($e->getMessage(), 0, 4000)])->save();
            throw $e;
        }
        return $delivery->fresh();
    }
}
