<?php

namespace Tests\Feature;

use Tests\TestCase;

class V13ReleaseCandidateSourceTest extends TestCase
{
    public function test_release_candidate_metadata_is_consistent(): void
    {
        $this->assertSame(
            '1.3.0-rc2',
            trim((string) file_get_contents(base_path('VERSION.txt')))
        );

        $phase = json_decode(
            (string) file_get_contents(base_path('PHASE.json')),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $this->assertSame('1.3.0-rc2', $phase['product_version'] ?? null);
        $this->assertSame('release-candidate', $phase['release_channel'] ?? null);
    }

    public function test_production_example_is_hardened_and_contains_no_demo_mode(): void
    {
        $env = (string) file_get_contents(base_path('.env.production.example'));

        $this->assertStringContainsString('APP_ENV=production', $env);
        $this->assertStringContainsString('APP_DEBUG=false', $env);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $env);
        $this->assertStringContainsString('FORCE_HTTPS=true', $env);
        $this->assertStringContainsString('SEED_DEMO_DATA=false', $env);
        $this->assertStringContainsString('JARINGANKU_VERSION=1.3.0-rc2', $env);
        $this->assertStringContainsString('RELEASE_CHANNEL=release-candidate', $env);
    }

    public function test_release_and_live_acceptance_tools_are_present(): void
    {
        foreach ([
            'scripts/release-candidate-check.sh',
            'scripts/prod-final-check.sh',
            'ops/07-v13-live-acceptance.sh',
            'docs/RELEASE-NOTES-V1.3.0-RC1.md',
            'docs/V1.3-FINAL-GO-LIVE-CHECKLIST.md',
        ] as $path) {
            $this->assertFileExists(base_path($path), $path.' must exist');
        }

        $prod = (string) file_get_contents(base_path('scripts/prod-final-check.sh'));
        $this->assertStringContainsString('jaringanku:phase15-security-audit --strict', $prod);
        $this->assertStringContainsString('jaringanku:network-acceptance --strict', $prod);
    }
}