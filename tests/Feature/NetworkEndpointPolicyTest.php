<?php

namespace Tests\Feature;

use App\Services\RouterEndpointPolicy;
use App\Services\WebhookUrlPolicy;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NetworkEndpointPolicyTest extends TestCase
{
    public function test_webhook_blocks_private_ipv4_and_ipv6_destinations(): void
    {
        $policy = app(WebhookUrlPolicy::class);

        foreach (['https://127.0.0.1/hook', 'https://[::1]/hook', 'https://169.254.169.254/latest/meta-data'] as $url) {
            try {
                $policy->validateOrFail($url);
                $this->fail('Private webhook destination should be rejected: '.$url);
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('url', $exception->errors());
            }
        }
    }

    public function test_webhook_disables_redirects_for_public_literal_ip(): void
    {
        $options = app(WebhookUrlPolicy::class)->httpOptions('https://93.184.216.34/hook');

        $this->assertFalse($options['allow_redirects']);
    }

    public function test_router_requires_allowed_cidr_port_and_tls(): void
    {
        config()->set('jaringanku.router_allowed_cidrs', '192.168.88.0/24');
        config()->set('jaringanku.router_allowed_rest_ports', '443');
        config()->set('jaringanku.router_allow_insecure_tls', false);
        $policy = app(RouterEndpointPolicy::class);

        $policy->validateOrFail('192.168.88.1', 443, true);
        $this->assertTrue(true);

        foreach ([['10.0.0.1', 443, true], ['192.168.88.1', 8728, true], ['192.168.88.1', 443, false]] as [$host, $port, $tls]) {
            try {
                $policy->validateOrFail($host, $port, $tls);
                $this->fail('Unsafe router endpoint should be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('host', $exception->errors());
            }
        }
    }
}
