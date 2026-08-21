<?php

namespace App\Services;

use App\Models\Router;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class MikrotikRestClient
{
    public function __construct(private readonly MikrotikTargetPolicy $targetPolicy) {}

    private function request(Router $router): PendingRequest
    {
        $request = Http::withBasicAuth($router->api_username, $router->api_password)
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(10);

        return $router->verify_tls ? $request : $request->withoutVerifying();
    }

    public function systemResource(Router $router): array
    {
        $port = (int) $router->rest_port;
        $target = $this->targetPolicy->resolveAllowedHostOrFail((string) $router->host);
        $this->targetPolicy->assertTlsPolicy((bool) $router->verify_tls);
        $host = str_contains($target['host'], ':') ? '['.$target['host'].']' : $target['host'];
        $base = sprintf('https://%s:%d/rest', $host, $port);
        $resolve = $this->targetPolicy->curlResolveEntries($target, $port);
        $request = $this->request($router)->withoutRedirecting();

        if ($resolve !== []) {
            $request = $request->withOptions(['curl' => [CURLOPT_RESOLVE => $resolve]]);
        }

        return $request
            ->get($base.'/system/resource')
            ->throw()
            ->json();
    }
}
