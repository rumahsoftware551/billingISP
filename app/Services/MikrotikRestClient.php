<?php

namespace App\Services;

use App\Models\Router;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class MikrotikRestClient
{
    private function request(Router $router): PendingRequest
    {
        $request = Http::withBasicAuth($router->api_username, $router->api_password)
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(10)
            ->retry(2, 250, throw: false);

        return $router->verify_tls ? $request : $request->withoutVerifying();
    }

    public function systemResource(Router $router): array
    {
        return $this->request($router)
            ->get($this->baseUrl($router).'/system/resource')
            ->throw()
            ->json();
    }

    private function baseUrl(Router $router): string
    {
        $host = trim((string) $router->host);
        if ($host === '' || str_contains($host, '://') || str_contains($host, '/') || str_contains($host, '?') || str_contains($host, '#') || str_contains($host, '@')) {
            throw new InvalidArgumentException('Host MikroTik tidak valid. Gunakan IP atau hostname tanpa skema/path.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $host = '['.$host.']';
        } elseif (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if (! preg_match('/^(?=.{1,253}$)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)*[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$/', $host)) {
                throw new InvalidArgumentException('Hostname MikroTik tidak valid.');
            }
        }

        $port = (int) $router->rest_port;
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Port REST MikroTik tidak valid.');
        }

        return sprintf('https://%s:%d/rest', $host, $port);
    }
}
