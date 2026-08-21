<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\WhatsAppSetting;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance(CurrentTenant::class);

        parent::tearDown();
    }

    public function test_receive_fails_closed_when_cloud_signing_is_not_configured(): void
    {
        $tenant = $this->tenant('whatsapp-no-secret');
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));

        WhatsAppSetting::query()->create([
            'mode' => 'cloud',
            'enabled' => true,
        ]);

        $this->postJson('/api/whatsapp/'.$tenant->slug.'/webhook', [
            'entry' => [],
        ])->assertNotFound();
    }

    public function test_receive_rejects_invalid_signature_and_accepts_valid_signature(): void
    {
        $tenant = $this->tenant('whatsapp-signature');
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));

        WhatsAppSetting::query()->create([
            'mode' => 'cloud',
            'enabled' => true,
            'app_secret' => 'whatsapp-test-signing-secret',
            'verify_token' => 'whatsapp-test-verify-token',
        ]);

        $payload = json_encode(['entry' => []], JSON_THROW_ON_ERROR);

        $this->call('POST', '/api/whatsapp/'.$tenant->slug.'/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalid',
        ], $payload)->assertUnauthorized();

        $signature = 'sha256='.hash_hmac('sha256', $payload, 'whatsapp-test-signing-secret');

        $this->call('POST', '/api/whatsapp/'.$tenant->slug.'/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $payload)
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    private function tenant(string $suffix): Tenant
    {
        return Tenant::query()->create([
            'name' => 'WhatsApp '.$suffix,
            'slug' => $suffix,
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);
    }
}
