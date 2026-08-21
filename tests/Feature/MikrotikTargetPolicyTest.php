<?php

namespace Tests\Feature;

use App\Services\MikrotikTargetPolicy;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MikrotikTargetPolicyTest extends TestCase
{
    public function test_router_target_requires_an_explicit_allowlist(): void
    {
        config()->set('jaringanku.mikrotik_allowed_cidrs', []);

        $this->expectValidationException(fn () => app(MikrotikTargetPolicy::class)->resolveAllowedHostOrFail('192.168.88.1'));
    }

    public function test_router_target_is_accepted_only_inside_the_allowlist(): void
    {
        config()->set('jaringanku.mikrotik_allowed_cidrs', ['192.168.88.0/24', '2001:db8:1234::/48']);

        $ipv4 = app(MikrotikTargetPolicy::class)->resolveAllowedHostOrFail('192.168.88.1');
        $ipv6 = app(MikrotikTargetPolicy::class)->resolveAllowedHostOrFail('2001:db8:1234::7');

        $this->assertSame(['192.168.88.1'], $ipv4['addresses']);
        $this->assertSame(['2001:db8:1234::7'], $ipv6['addresses']);
    }

    public function test_router_target_outside_the_allowlist_is_rejected(): void
    {
        config()->set('jaringanku.mikrotik_allowed_cidrs', ['192.168.88.0/24']);

        $this->expectValidationException(fn () => app(MikrotikTargetPolicy::class)->resolveAllowedHostOrFail('169.254.169.254'));
    }

    private function expectValidationException(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected target validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('host', $exception->errors());
        }
    }
}
