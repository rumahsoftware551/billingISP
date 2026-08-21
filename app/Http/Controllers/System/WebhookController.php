<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\WebhookEndpoint;
use App\Services\AuditService;
use App\Services\WebhookService;
use App\Services\WebhookUrlPolicy;
use App\Support\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WebhookController extends Controller
{
    public function store(Request $request, AuditService $audit, WebhookUrlPolicy $urlPolicy)
    {
        $data = $this->validated($request);
        $urlPolicy->validateOrFail($data['url']);
        $generatedSecret = blank($data['secret'] ?? null) ? Str::random(48) : null;
        $endpoint = WebhookEndpoint::create([
            ...$data,
            'secret' => $generatedSecret ?: $data['secret'],
        ]);

        $audit->record('webhook.created', WebhookEndpoint::class, $endpoint->id, null, [
            'name' => $endpoint->name,
            'url' => $endpoint->url,
            'events' => $endpoint->events,
            'enabled' => $endpoint->enabled,
        ]);

        $response = back()->with('success', 'Webhook endpoint dibuat. Secret disimpan terenkripsi.');
        if ($generatedSecret !== null) {
            $response->with('generated_webhook_secret', $generatedSecret);
        }
        return $response;
    }

    public function update(Request $request, int $webhookId, AuditService $audit, WebhookUrlPolicy $urlPolicy)
    {
        $webhook = WebhookEndpoint::query()->findOrFail($webhookId);
        $old = $webhook->only(['name', 'url', 'events', 'enabled', 'timeout_seconds', 'max_attempts']);
        $data = $this->validated($request, $webhook->id);
        $urlPolicy->validateOrFail($data['url']);
        if (blank($data['secret'] ?? null)) {
            unset($data['secret']);
        }
        $webhook->update($data);

        $audit->record('webhook.updated', WebhookEndpoint::class, $webhook->id, $old, $webhook->fresh()->only(['name', 'url', 'events', 'enabled', 'timeout_seconds', 'max_attempts']));
        return back()->with('success', 'Webhook endpoint diperbarui.');
    }

    public function destroy(int $webhookId, AuditService $audit)
    {
        $webhook = WebhookEndpoint::query()->findOrFail($webhookId);
        $audit->record('webhook.deleted', WebhookEndpoint::class, $webhook->id, $webhook->only(['name', 'url', 'events', 'enabled']), null);
        $webhook->delete();
        return back()->with('success', 'Webhook endpoint dihapus.');
    }

    public function test(int $webhookId, WebhookService $webhooks, AuditService $audit)
    {
        $webhook = WebhookEndpoint::query()->findOrFail($webhookId);
        $tenant = app(CurrentTenant::class)->tenant;
        $delivery = $webhooks->emitToEndpoint($tenant, $webhook, 'system.test', [
            'message' => 'Jaringanku webhook test',
            'endpoint_id' => $webhook->id,
        ]);

        $audit->record('webhook.test_dispatched', WebhookEndpoint::class, $webhook->id, null, ['delivery_id' => $delivery->id]);
        return back()->with('success', 'Webhook delivery masuk ke queue.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('webhook_endpoints', 'name')->where('tenant_id', app(CurrentTenant::class)->id())->ignore($ignoreId)],
            'url' => ['required', 'url:http,https', 'max:2000'],
            'secret' => ['nullable', 'string', 'min:16', 'max:255'],
            'events' => ['required', 'array', 'min:1', 'max:30'],
            'events.*' => ['required', 'string', 'max:120'],
            'enabled' => ['required', 'boolean'],
            'timeout_seconds' => ['required', 'integer', 'between:1,30'],
            'max_attempts' => ['required', 'integer', 'between:1,5'],
        ]);
    }
}
