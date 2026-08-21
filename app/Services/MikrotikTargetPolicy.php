<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/**
 * Restricts MikroTik REST requests to explicitly approved network ranges.
 *
 * Router credentials are highly privileged. Treating a user-supplied host as
 * an arbitrary outbound URL would otherwise let an administrator probe the
 * application's internal network or cloud metadata services.
 */
final class MikrotikTargetPolicy
{
    /**
     * @return array{host: string, addresses: array<int, string>}
     */
    public function resolveAllowedHostOrFail(string $host): array
    {
        $host = $this->normalizeHost($host);
        $addresses = $this->resolve($host);
        $cidrs = $this->allowedCidrs();

        if ($cidrs === []) {
            throw ValidationException::withMessages([
                'host' => 'MIKROTIK_ALLOWED_CIDRS wajib diatur sebelum router dapat ditambahkan atau dihubungi.',
            ]);
        }

        foreach ($addresses as $address) {
            if (! $this->matchesAnyCidr($address, $cidrs)) {
                throw ValidationException::withMessages([
                    'host' => 'Alamat router tidak termasuk MIKROTIK_ALLOWED_CIDRS.',
                ]);
            }
        }

        return ['host' => $host, 'addresses' => $addresses];
    }

    public function assertTlsPolicy(bool $verifyTls): void
    {
        if (app()->environment('production')
            && (bool) config('jaringanku.mikrotik_require_tls', true)
            && ! $verifyTls) {
            throw ValidationException::withMessages([
                'verify_tls' => 'Verifikasi sertifikat TLS MikroTik wajib aktif di production.',
            ]);
        }
    }

    /** @return array<int, string> */
    public function curlResolveEntries(array $target, int $port): array
    {
        if (filter_var($target['host'], FILTER_VALIDATE_IP)) {
            return [];
        }

        return array_map(
            fn (string $address) => $target['host'].':'.$port.':'.$address,
            $target['addresses'],
        );
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(rtrim(trim($host), '.'));
        $host = trim($host, '[]');

        if ($host === '') {
            throw ValidationException::withMessages(['host' => 'Host MikroTik wajib diisi.']);
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }

        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            throw ValidationException::withMessages(['host' => 'Host MikroTik harus berupa hostname atau alamat IP yang valid.']);
        }

        return $host;
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

        $addresses = array_values(array_unique($addresses));
        if ($addresses === []) {
            throw ValidationException::withMessages(['host' => 'Hostname MikroTik tidak dapat di-resolve.']);
        }

        return $addresses;
    }

    /** @return array<int, string> */
    private function allowedCidrs(): array
    {
        $configured = config('jaringanku.mikrotik_allowed_cidrs', []);
        $items = is_array($configured) ? $configured : [$configured];
        $cidrs = [];

        foreach ($items as $item) {
            foreach (explode(',', (string) $item) as $cidr) {
                $cidr = trim($cidr);
                if ($cidr === '') {
                    continue;
                }
                if (! $this->isValidCidr($cidr)) {
                    throw ValidationException::withMessages([
                        'host' => 'MIKROTIK_ALLOWED_CIDRS berisi CIDR yang tidak valid: '.$cidr,
                    ]);
                }
                $cidrs[] = $cidr;
            }
        }

        return array_values(array_unique($cidrs));
    }

    /** @param array<int, string> $cidrs */
    private function matchesAnyCidr(string $address, array $cidrs): bool
    {
        foreach ($cidrs as $cidr) {
            if ($this->cidrContains($cidr, $address)) {
                return true;
            }
        }

        return false;
    }

    private function isValidCidr(string $cidr): bool
    {
        [$network, $prefix] = array_pad(explode('/', $cidr, 2), 2, null);
        if ($prefix === null || filter_var($network, FILTER_VALIDATE_IP) === false || ! ctype_digit($prefix)) {
            return false;
        }

        $maxPrefix = str_contains($network, ':') ? 128 : 32;

        return (int) $prefix >= 0 && (int) $prefix <= $maxPrefix;
    }

    private function cidrContains(string $cidr, string $address): bool
    {
        [$network, $prefix] = explode('/', $cidr, 2);
        if ((str_contains($network, ':')) !== (str_contains($address, ':'))) {
            return false;
        }

        $networkBytes = inet_pton($network);
        $addressBytes = inet_pton($address);
        if ($networkBytes === false || $addressBytes === false) {
            return false;
        }

        $prefix = (int) $prefix;
        $wholeBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        if ($wholeBytes > 0 && substr($networkBytes, 0, $wholeBytes) !== substr($addressBytes, 0, $wholeBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xff << (8 - $remainingBits)) & 0xff;

        return (ord($networkBytes[$wholeBytes]) & $mask) === (ord($addressBytes[$wholeBytes]) & $mask);
    }
}
