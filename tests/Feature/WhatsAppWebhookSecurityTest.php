<?php

namespace Tests\Feature;

use App\Models\WhatsAppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_callback_fails_closed_when_app_secret_is_missing(): void
    {
        $tenant = $this->createTenant();
        WhatsAppSetting::query()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'meta_cloud',
            'mode' => 'cloud',
            'enabled' => true,
        ]);

        $this->postJson('/api/whatsapp/'.$tenant->slug.'/webhook', ['entry' => []])
            ->assertNotFound();
    }

    public function test_callback_requires_valid_signature(): void
    {
        $tenant = $this->createTenant();
        $secret = Str::random(40);
        WhatsAppSetting::query()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'meta_cloud',
            'mode' => 'cloud',
            'enabled' => true,
            'app_secret' => $secret,
        ]);

        $this->postJson('/api/whatsapp/'.$tenant->slug.'/webhook', ['entry' => []])
            ->assertUnauthorized();

        $body = json_encode(['entry' => []], JSON_THROW_ON_ERROR);
        $signature = 'sha256='.hash_hmac('sha256', $body, $secret);
        $this->call(
            'POST',
            '/api/whatsapp/'.$tenant->slug.'/webhook',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $signature],
            $body,
        )->assertOk()->assertJson(['ok' => true]);
    }
}
