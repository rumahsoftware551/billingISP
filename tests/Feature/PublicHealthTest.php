<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicHealthTest extends TestCase
{
    public function test_live_health_endpoint_is_available(): void
    {
        $response = $this->getJson('/health/live');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure([
                'status',
                'time',
            ]);
    }

    public function test_live_health_endpoint_has_security_headers(): void
    {
        $response = $this->getJson('/health/live');

        $response
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');

        $this->assertNotEmpty(
            $response->headers->get('X-Request-Id')
        );
    }
}