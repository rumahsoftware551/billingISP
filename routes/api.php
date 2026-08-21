<?php

use App\Models\WebhookDelivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MidtransNotificationController;
use App\Http\Controllers\Api\WhatsAppWebhookController;

Route::get('/health', fn () => ['ok' => true, 'app' => 'Jaringanku', 'phase' => 10, 'time' => now()->toIso8601String()]);
Route::post('/payments/midtrans/notification', MidtransNotificationController::class)->middleware('throttle:120,1');
Route::get('/whatsapp/{tenantSlug}/webhook', [WhatsAppWebhookController::class, 'verify'])->middleware('throttle:60,1');
Route::post('/whatsapp/{tenantSlug}/webhook', [WhatsAppWebhookController::class, 'receive'])->middleware('throttle:240,1');

Route::post('/phase8-smoke/webhook', function (Request $request) {
    abort_unless(app()->environment('local'), 404);

    $expectedToken = (string) config('jaringanku.phase08_smoke_token', 'phase08-local-smoke');
    abort_unless(hash_equals($expectedToken, (string) $request->header('X-Phase08-Smoke-Token')), 404);

    $eventId = (string) $request->header('X-Jaringanku-Event-Id', '');
    $timestamp = (string) $request->header('X-Jaringanku-Timestamp', '');
    $signature = (string) $request->header('X-Jaringanku-Signature', '');
    abort_if($eventId === '' || $timestamp === '' || $signature === '', 400, 'Webhook signature headers are required.');

    $delivery = WebhookDelivery::query()
        ->withoutGlobalScopes()
        ->with('endpoint')
        ->where('event_id', $eventId)
        ->first();
    abort_unless($delivery?->endpoint, 404);

    $expectedSignature = 'sha256='.hash_hmac(
        'sha256',
        $timestamp.'.'.$request->getContent(),
        (string) $delivery->endpoint->secret,
    );
    abort_unless(hash_equals($expectedSignature, $signature), 401, 'Invalid webhook signature.');
    abort_unless(hash_equals((string) $delivery->event, (string) $request->header('X-Jaringanku-Event', '')), 400, 'Event header mismatch.');

    Cache::put('jaringanku:phase08:webhook:'.$eventId, [
        'verified' => true,
        'received_at' => now()->toIso8601String(),
    ], now()->addMinutes(5));

    return response()->json(['ok' => true, 'verified' => true, 'event_id' => $eventId]);
});
