<?php

namespace App\Services;

use RuntimeException;

class NetworkAddressPolicy
{
    /** @return list<string> */
    public function resolveAll(string $host): array
    {
        $host = trim($host, '[]');
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false) {
            throw new RuntimeException('Hostname tidak dapat di-resolve.');
        }

        $addresses = [];
        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($address) && filter_var($address, FILTER_VALIDATE_IP)) {
                $addresses[] = $address;
            }
        }

        $addresses = array_values(array_unique($addresses));
        if ($addresses === []) {
            throw new RuntimeException('Hostname tidak memiliki record A/AAAA yang valid.');
        }

        return $addresses;
    }

    public function isPublic(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    /** @param list<string> $cidrs */
    public function isInAnyCidr(string $address, array $cidrs): bool
    {
        foreach ($cidrs as $cidr) {
            if ($this->isInCidr($address, $cidr)) {
                return true;
            }
        }

        return false;
    }

    private function isInCidr(string $address, string $cidr): bool
    {
        [$network, $prefix] = array_pad(explode('/', trim($cidr), 2), 2, null);
        $addressBytes = @inet_pton($address);
        $networkBytes = @inet_pton((string) $network);
        if ($addressBytes === false || $networkBytes === false || strlen($addressBytes) !== strlen($networkBytes)) {
            return false;
        }

        $maxBits = strlen($addressBytes) * 8;
        $prefixBits = $prefix === null ? $maxBits : filter_var($prefix, FILTER_VALIDATE_INT);
        if ($prefixBits === false || $prefixBits < 0 || $prefixBits > $maxBits) {
            return false;
        }

        $wholeBytes = intdiv($prefixBits, 8);
        $remainingBits = $prefixBits % 8;
        if ($wholeBytes > 0 && substr($addressBytes, 0, $wholeBytes) !== substr($networkBytes, 0, $wholeBytes)) {
            return false;
        }
        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xff << (8 - $remainingBits)) & 0xff;
        return (ord($addressBytes[$wholeBytes]) & $mask) === (ord($networkBytes[$wholeBytes]) & $mask);
    }
}
