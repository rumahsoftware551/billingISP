<?php

namespace Tests\Feature;

use App\Services\WebhookUrlPolicy;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WebhookUrlPolicySecurityTest extends TestCase
{
    public function test_private_reserved_and_ipv6_loopback_targets_are_rejected(): void
    {
        config()->set('jaringanku.webhook_allow_private_networks', false);

        foreach ([
            'https://127.0.0.1/hook',
            'https://169.254.169.254/latest/meta-data',
            'https://[::1]/hook',
            'https://[fc00::1]/hook',
            'https://localhost/hook',
        ] as $url) {
            try {
                app(WebhookUrlPolicy::class)->validateOrFail($url);
                self::fail('Target harus ditolak: '.$url);
            } catch (ValidationException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_delivery_job_revalidates_target_and_does_not_follow_redirects(): void
    {
        $source = file_get_contents(base_path('app/Jobs/DeliverWebhookJob.php'));
        $policy = file_get_contents(base_path('app/Services/WebhookUrlPolicy.php'));

        self::assertStringContainsString('validateOrFail', $source);
        self::assertStringContainsString('withoutRedirecting()', $source);
        self::assertStringContainsString('DNS_A | DNS_AAAA', $policy);
        self::assertStringContainsString('FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE', $policy);
    }
}
