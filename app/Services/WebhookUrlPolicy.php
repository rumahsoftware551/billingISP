<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class WebhookUrlPolicy
{
    public function validateOrFail(string $url): void
    {
        $parts = parse_url($url);
        if ($parts === false) {
            throw ValidationException::withMessages(['url' => 'Webhook URL harus berupa HTTP/HTTPS URL yang valid.']);
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw ValidationException::withMessages(['url' => 'Webhook URL harus berupa HTTP/HTTPS URL yang valid.']);
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw ValidationException::withMessages(['url' => 'Webhook URL tidak boleh mengandung username/password.']);
        }

        if (app()->environment('production') && $scheme !== 'https' && ! (bool) config('jaringanku.webhook_allow_insecure_http', false)) {
            throw ValidationException::withMessages(['url' => 'Production webhook harus menggunakan HTTPS.']);
        }

        if (app()->environment('local') || (bool) config('jaringanku.webhook_allow_private_networks', false)) {
            return;
        }

        if (in_array($host, ['localhost', 'localhost.localdomain'], true) || str_ends_with($host, '.localhost')) {
            throw ValidationException::withMessages(['url' => 'Webhook ke localhost/private network diblokir.']);
        }

        $addresses = $this->resolveAddresses($host);

        if ($addresses === []) {
            throw ValidationException::withMessages(['url' => 'Hostname webhook tidak dapat di-resolve.']);
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicAddress($address)) {
                throw ValidationException::withMessages(['url' => 'Webhook ke private/reserved IP diblokir.']);
            }
        }
    }

    /** @return list<string> */
    private function resolveAddresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $addresses = [];
        if (function_exists('dns_get_record')) {
            foreach (dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
                foreach (['ip', 'ipv6'] as $field) {
                    if (isset($record[$field]) && filter_var($record[$field], FILTER_VALIDATE_IP)) {
                        $addresses[] = $record[$field];
                    }
                }
            }
        }

        // Fallback retains IPv4 resolution on minimal PHP builds. A hostname
        // resolving only to IPv6 remains fail-closed when DNS_AAAA is absent.
        if ($addresses === []) {
            $addresses = gethostbynamel($host) ?: [];
        }

        return array_values(array_unique($addresses));
    }

    private function isPublicAddress(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
