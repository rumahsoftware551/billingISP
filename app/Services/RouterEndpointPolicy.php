<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use Throwable;

class RouterEndpointPolicy
{
    public function __construct(private NetworkAddressPolicy $addresses) {}

    public function validateOrFail(string $host, int $port, bool $verifyTls): void
    {
        $host = trim($host, '[]');
        if ($host === '' || str_contains($host, '%')) {
            $this->fail('Host MikroTik tidak valid.');
        }

        if (app()->environment('production') && ! filter_var($host, FILTER_VALIDATE_IP)) {
            $this->fail('Production mewajibkan host MikroTik berupa IP literal untuk mencegah DNS rebinding.');
        }

        $allowedPorts = $this->csvIntegers((string) config('jaringanku.router_allowed_rest_ports', ''));
        if (app()->environment('production') && $allowedPorts === []) {
            $this->fail('MIKROTIK_ALLOWED_REST_PORTS wajib dikonfigurasi untuk production.');
        }
        if ($allowedPorts !== [] && ! in_array($port, $allowedPorts, true)) {
            $this->fail('Port REST MikroTik tidak termasuk allowlist production.');
        }

        if (! $verifyTls && ! (bool) config('jaringanku.router_allow_insecure_tls', false)) {
            $this->fail('Verifikasi TLS MikroTik wajib aktif.');
        }

        $cidrs = $this->csv((string) config('jaringanku.router_allowed_cidrs', ''));
        if (app()->environment('production') && ($cidrs === [] || collect($cidrs)->contains(fn (string $cidr) => str_starts_with($cidr, 'CHANGE_ME')))) {
            $this->fail('MIKROTIK_ALLOWED_CIDRS wajib dikonfigurasi sebelum koneksi router production.');
        }
        if ($cidrs === []) {
            return;
        }

        try {
            $resolved = $this->addresses->resolveAll($host);
        } catch (Throwable $e) {
            $this->fail($e->getMessage());
        }

        foreach ($resolved as $address) {
            if (! $this->addresses->isInAnyCidr($address, $cidrs)) {
                $this->fail('IP MikroTik berada di luar MIKROTIK_ALLOWED_CIDRS.');
            }
        }
    }

    /** @return list<string> */
    private function csv(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value)), fn (string $item) => $item !== ''));
    }

    /** @return list<int> */
    private function csvIntegers(string $value): array
    {
        return array_values(array_filter(array_map(
            fn (string $item) => filter_var(trim($item), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) ?: null,
            explode(',', $value),
        )));
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['host' => $message]);
    }
}
