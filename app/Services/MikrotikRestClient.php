<?php

namespace App\Services;

use App\Models\Router;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class MikrotikRestClient
{
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
        $base = sprintf('https://%s:%d/rest', $router->host, $router->rest_port);

        return $this->request($router)
            ->get($base.'/system/resource')
            ->throw()
            ->json();
    }
}
