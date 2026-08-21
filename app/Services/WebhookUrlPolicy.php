<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class WebhookUrlPolicy
{
    /**
     * @return array{host: string, port: int, addresses: array<int, string>}
     */
    public function validateOrFail(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = trim(strtolower((string) ($parts['host'] ?? '')), '[]');
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw ValidationException::withMessages(['url' => 'Webhook URL harus berupa HTTP/HTTPS URL yang valid.']);
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw ValidationException::withMessages(['url' => 'Webhook URL tidak boleh mengandung username/password.']);
        }

        if ($port < 1 || $port > 65535) {
            throw ValidationException::withMessages(['url' => 'Webhook URL menggunakan port yang tidak valid.']);
        }

        if (app()->environment('production') && $scheme !== 'https' && ! (bool) config('jaringanku.webhook_allow_insecure_http', false)) {
            throw ValidationException::withMessages(['url' => 'Production webhook harus menggunakan HTTPS.']);
        }

        if (app()->environment('local') || (bool) config('jaringanku.webhook_allow_private_networks', false)) {
            return ['host' => $host, 'port' => $port, 'addresses' => []];
        }

        if (in_array($host, ['localhost', 'localhost.localdomain'], true) || str_ends_with($host, '.localhost')) {
            throw ValidationException::withMessages(['url' => 'Webhook ke localhost/private network diblokir.']);
        }

        $addresses = $this->resolve($host);

        if ($addresses === []) {
            throw ValidationException::withMessages(['url' => 'Hostname webhook tidak dapat di-resolve.']);
        }

        foreach ($addresses as $address) {
            $public = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if ($public === false) {
                throw ValidationException::withMessages(['url' => 'Webhook ke private/reserved IP diblokir.']);
            }
        }

        return ['host' => $host, 'port' => $port, 'addresses' => $addresses];
    }

    /**
     * Pin a hostname to the same addresses that passed the SSRF policy. This
     * prevents a second DNS lookup from turning a safe hostname into an
     * internal target between validation and connection.
     *
     * @param array{host: string, port: int, addresses: array<int, string>} $target
     * @return array<int, string>
     */
    public function curlResolveEntries(array $target): array
    {
        if ($target['addresses'] === [] || filter_var($target['host'], FILTER_VALIDATE_IP)) {
            return [];
        }

        return array_map(function (string $address) use ($target): string {
            $address = str_contains($address, ':') ? '['.$address.']' : $address;

            return $target['host'].':'.$target['port'].':'.$address;
        }, $target['addresses']);
    }

    /** @return array<int, string> */
    private function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        $addresses = [];

        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($address) && filter_var($address, FILTER_VALIDATE_IP)) {
                $addresses[] = $address;
            }
        }

        return array_values(array_unique($addresses));
    }
}
