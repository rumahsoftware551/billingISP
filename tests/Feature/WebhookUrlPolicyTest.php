<?php

namespace Tests\Feature;

use App\Services\WebhookUrlPolicy;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WebhookUrlPolicyTest extends TestCase
{
    public function test_private_ipv4_and_ipv6_webhook_targets_are_rejected(): void
    {
        config()->set('jaringanku.webhook_allow_private_networks', false);
        $policy = app(WebhookUrlPolicy::class);

        $this->expectValidationException(fn () => $policy->validateOrFail('https://127.0.0.1/hook'));
        $this->expectValidationException(fn () => $policy->validateOrFail('https://[::1]/hook'));
    }

    public function test_public_ip_target_produces_a_resolved_target(): void
    {
        config()->set('jaringanku.webhook_allow_private_networks', false);

        $target = app(WebhookUrlPolicy::class)->validateOrFail('https://8.8.8.8:8443/hook');

        $this->assertSame('8.8.8.8', $target['host']);
        $this->assertSame(8443, $target['port']);
        $this->assertSame(['8.8.8.8'], $target['addresses']);
    }

    private function expectValidationException(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected webhook target validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('url', $exception->errors());
        }
    }
}
