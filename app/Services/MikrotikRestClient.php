<?php

namespace App\Services;

use App\Models\Router;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class MikrotikRestClient
{
    public function __construct(private RouterEndpointPolicy $endpointPolicy) {}

    private function request(Router $router): PendingRequest
    {
        $this->endpointPolicy->validateOrFail($router->host, (int) $router->rest_port, (bool) $router->verify_tls);
        $request = Http::withBasicAuth($router->api_username, $router->api_password)
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(10);

        return $router->verify_tls ? $request : $request->withoutVerifying();
    }

    public function systemResource(Router $router): array
    {
        $host = filter_var($router->host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '['.$router->host.']' : $router->host;
        $base = sprintf('https://%s:%d/rest', $host, $router->rest_port);

        return $this->request($router)
            ->get($base.'/system/resource')
            ->throw()
            ->json();
    }
}
