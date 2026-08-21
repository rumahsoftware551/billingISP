<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\WhatsAppMessageLog;
use App\Models\WhatsAppSetting;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request, string $tenantSlug): Response
    {
        $setting = $this->setting($tenantSlug);
        abort_unless($request->query('hub_mode') === 'subscribe' || $request->query('hub.mode') === 'subscribe', 403);

        $token = (string) ($request->query('hub_verify_token') ?? $request->query('hub.verify_token', ''));
        abort_unless(filled($setting->verify_token) && hash_equals((string) $setting->verify_token, $token), 403);

        return response((string) ($request->query('hub_challenge') ?? $request->query('hub.challenge', '')), 200)
            ->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request, string $tenantSlug): JsonResponse
    {
        $setting = $this->setting($tenantSlug);

        // A public callback must never become unauthenticated because configuration is
        // incomplete. Disabled/log integrations and missing secrets deliberately look absent.
        abort_unless($setting->enabled && $setting->mode === 'cloud' && filled($setting->app_secret), 404);

        $signature = (string) $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), (string) $setting->app_secret);
        abort_unless($signature !== '' && hash_equals($expected, $signature), 401);

        $payload = $request->json()->all();
        foreach (data_get($payload, 'entry', []) as $entry) {
            foreach (data_get($entry, 'changes', []) as $change) {
                foreach (data_get($change, 'value.statuses', []) as $status) {
                    $providerMessageId = (string) ($status['id'] ?? '');
                    if ($providerMessageId === '') {
                        continue;
                    }

                    WhatsAppMessageLog::query()
                        ->where('provider_message_id', $providerMessageId)
                        ->update([
                            'status' => (string) ($status['status'] ?? 'unknown'),
                            'response' => $status,
                            'updated_at' => now(),
                        ]);
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    private function setting(string $tenantSlug): WhatsAppSetting
    {
        $tenant = Tenant::query()->where('slug', $tenantSlug)->where('status', 'active')->firstOrFail();
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));

        return WhatsAppSetting::query()->firstOrFail();
    }
}
