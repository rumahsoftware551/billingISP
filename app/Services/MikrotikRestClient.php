<?php

namespace App\Services;

use App\Models\Router;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * RouterOS compatibility client.
 *
 * Kept under the historical MikrotikRestClient class name to avoid breaking
 * existing container bindings. RC4 supports both RouterOS v6/v7 classic API
 * (8728/8729) and RouterOS v7 REST.
 */
class MikrotikRestClient
{
    public function systemResource(Router $router): array
    {
        return match ((string) ($router->api_driver ?: 'rest')) {
            'api' => $this->classicSystemResource($router, false),
            'api_ssl' => $this->classicSystemResource($router, true),
            'rest' => $this->restSystemResource($router),
            default => throw new InvalidArgumentException('Driver MikroTik tidak didukung.'),
        };
    }

    public function driverLabel(Router $router): string
    {
        return match ((string) ($router->api_driver ?: 'rest')) {
            'api' => 'Classic API',
            'api_ssl' => 'API-SSL',
            'rest' => 'REST HTTPS',
            default => 'unknown driver',
        };
    }

    private function restSystemResource(Router $router): array
    {
        $payload = $this->request($router)
            ->get($this->baseUrl($router).'/system/resource')
            ->throw()
            ->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Respons REST MikroTik tidak valid.');
        }

        // RouterOS REST print endpoints may return either one object or a list.
        if (array_is_list($payload)) {
            return isset($payload[0]) && is_array($payload[0]) ? $payload[0] : [];
        }

        return $payload;
    }

    private function request(Router $router): PendingRequest
    {
        $request = Http::withBasicAuth($router->api_username, $router->api_password)
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(10)
            ->retry(2, 250, throw: false);

        return $router->verify_tls ? $request : $request->withoutVerifying();
    }

    private function baseUrl(Router $router): string
    {
        $host = $this->validateHost((string) $router->host);
        $port = (int) $router->rest_port;

        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Port REST MikroTik tidak valid.');
        }

        return sprintf('https://%s:%d/rest', $this->socketHost($host), $port);
    }

    private function classicSystemResource(Router $router, bool $tls): array
    {
        $socket = $this->openClassicSocket($router, $tls);

        try {
            $this->login($socket, (string) $router->api_username, (string) $router->api_password);
            $this->writeSentence($socket, ['/system/resource/print']);

            $sentences = $this->readUntilDone($socket);
            foreach ($sentences as $sentence) {
                if (($sentence[0] ?? null) === '!re') {
                    return $this->properties($sentence);
                }
            }

            throw new RuntimeException('RouterOS API tidak mengembalikan /system/resource.');
        } finally {
            fclose($socket);
        }
    }

    /** @return resource */
    private function openClassicSocket(Router $router, bool $tls)
    {
        $host = $this->validateHost((string) $router->host);
        $port = (int) $router->api_port;

        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Port RouterOS API tidak valid.');
        }

        $verifyTls = (bool) $router->verify_tls;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => $verifyTls,
                'verify_peer_name' => $verifyTls,
                'allow_self_signed' => ! $verifyTls,
                'SNI_enabled' => true,
                'peer_name' => $host,
            ],
        ]);

        $transport = $tls ? 'tls' : 'tcp';
        $target = sprintf('%s://%s:%d', $transport, $this->socketHost($host), $port);
        $errno = 0;
        $error = '';

        $socket = @stream_socket_client(
            $target,
            $errno,
            $error,
            8,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if (! is_resource($socket)) {
            throw new RuntimeException(sprintf('Tidak dapat terhubung ke MikroTik %s:%d (%s).', $host, $port, $error ?: 'connection failed'));
        }

        stream_set_timeout($socket, 10);

        return $socket;
    }

    /** @param resource $socket */
    private function login($socket, string $username, string $password): void
    {
        $this->writeSentence($socket, ['/login', '=name='.$username, '=password='.$password]);
        $sentences = $this->readUntilDone($socket);

        $trap = $this->trapMessage($sentences);
        if ($trap !== null) {
            throw new RuntimeException('Login RouterOS API ditolak: '.$trap);
        }

        $done = $this->doneProperties($sentences);
        $challenge = $done['ret'] ?? null;

        // Legacy RouterOS fallback. RouterOS >= 6.43 accepts name/password directly.
        if (is_string($challenge) && $challenge !== '') {
            $binaryChallenge = @hex2bin($challenge);
            if ($binaryChallenge === false) {
                throw new RuntimeException('Challenge login RouterOS API tidak valid.');
            }

            $response = '00'.md5(chr(0).$password.$binaryChallenge);
            $this->writeSentence($socket, ['/login', '=name='.$username, '=response='.$response]);
            $sentences = $this->readUntilDone($socket);

            $trap = $this->trapMessage($sentences);
            if ($trap !== null) {
                throw new RuntimeException('Login legacy RouterOS API ditolak: '.$trap);
            }
        }
    }

    /** @param resource $socket @param array<int,string> $words */
    private function writeSentence($socket, array $words): void
    {
        foreach ($words as $word) {
            $payload = $this->encodeLength(strlen($word)).$word;
            $this->writeAll($socket, $payload);
        }

        $this->writeAll($socket, "\x00");
    }

    /** @param resource $socket */
    private function writeAll($socket, string $payload): void
    {
        $offset = 0;
        $length = strlen($payload);

        while ($offset < $length) {
            $written = fwrite($socket, substr($payload, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Gagal menulis paket RouterOS API.');
            }
            $offset += $written;
        }
    }

    /** @param resource $socket @return array<int,array<int,string>> */
    private function readUntilDone($socket): array
    {
        $sentences = [];

        while (true) {
            $sentence = $this->readSentence($socket);
            if ($sentence === []) {
                throw new RuntimeException('Koneksi RouterOS API ditutup sebelum respons selesai.');
            }

            $sentences[] = $sentence;
            $type = $sentence[0] ?? '';

            if ($type === '!done') {
                return $sentences;
            }

            if ($type === '!fatal') {
                throw new RuntimeException('RouterOS API fatal: '.($this->properties($sentence)['message'] ?? 'unknown error'));
            }
        }
    }

    /** @param resource $socket @return array<int,string> */
    private function readSentence($socket): array
    {
        $words = [];

        while (true) {
            $length = $this->readLength($socket);
            if ($length === 0) {
                return $words;
            }

            if ($length > 16 * 1024 * 1024) {
                throw new RuntimeException('Word RouterOS API terlalu besar.');
            }

            $words[] = $this->readExact($socket, $length);
        }
    }

    /** @param resource $socket */
    private function readLength($socket): int
    {
        $first = ord($this->readExact($socket, 1));

        if (($first & 0x80) === 0x00) {
            return $first;
        }

        if (($first & 0xC0) === 0x80) {
            return (($first & 0x3F) << 8) | ord($this->readExact($socket, 1));
        }

        if (($first & 0xE0) === 0xC0) {
            $bytes = $this->readExact($socket, 2);
            return (($first & 0x1F) << 16) | (ord($bytes[0]) << 8) | ord($bytes[1]);
        }

        if (($first & 0xF0) === 0xE0) {
            $bytes = $this->readExact($socket, 3);
            return (($first & 0x0F) << 24) | (ord($bytes[0]) << 16) | (ord($bytes[1]) << 8) | ord($bytes[2]);
        }

        if (($first & 0xF8) === 0xF0) {
            $bytes = unpack('Nlength', $this->readExact($socket, 4));
            return (int) $bytes['length'];
        }

        throw new RuntimeException('Length prefix RouterOS API tidak valid.');
    }

    /** @param resource $socket */
    private function readExact($socket, int $length): string
    {
        $buffer = '';

        while (strlen($buffer) < $length) {
            $chunk = fread($socket, $length - strlen($buffer));
            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($socket);
                $reason = ! empty($meta['timed_out']) ? 'timeout' : 'connection closed';
                throw new RuntimeException('Gagal membaca RouterOS API: '.$reason.'.');
            }
            $buffer .= $chunk;
        }

        return $buffer;
    }

    private function encodeLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        if ($length < 0x4000) {
            $length |= 0x8000;
            return chr(($length >> 8) & 0xFF).chr($length & 0xFF);
        }

        if ($length < 0x200000) {
            $length |= 0xC00000;
            return chr(($length >> 16) & 0xFF).chr(($length >> 8) & 0xFF).chr($length & 0xFF);
        }

        if ($length < 0x10000000) {
            $length |= 0xE0000000;
            return chr(($length >> 24) & 0xFF).chr(($length >> 16) & 0xFF).chr(($length >> 8) & 0xFF).chr($length & 0xFF);
        }

        return "\xF0".pack('N', $length);
    }

    /** @param array<int,string> $sentence @return array<string,string> */
    private function properties(array $sentence): array
    {
        $properties = [];

        foreach ($sentence as $word) {
            if (! str_starts_with($word, '=')) {
                continue;
            }

            $pair = explode('=', substr($word, 1), 2);
            $properties[$pair[0]] = $pair[1] ?? '';
        }

        return $properties;
    }

    /** @param array<int,array<int,string>> $sentences @return array<string,string> */
    private function doneProperties(array $sentences): array
    {
        foreach (array_reverse($sentences) as $sentence) {
            if (($sentence[0] ?? null) === '!done') {
                return $this->properties($sentence);
            }
        }

        return [];
    }

    /** @param array<int,array<int,string>> $sentences */
    private function trapMessage(array $sentences): ?string
    {
        foreach ($sentences as $sentence) {
            if (($sentence[0] ?? null) === '!trap') {
                return $this->properties($sentence)['message'] ?? 'unknown error';
            }
        }

        return null;
    }

    private function validateHost(string $host): string
    {
        $host = trim($host);

        if ($host === '' || str_contains($host, '://') || str_contains($host, '/') || str_contains($host, '?') || str_contains($host, '#') || str_contains($host, '@')) {
            throw new InvalidArgumentException('Host MikroTik tidak valid. Gunakan IP atau hostname tanpa skema/path.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $host;
        }

        if (! preg_match('/^(?=.{1,253}$)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)*[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$/', $host)) {
            throw new InvalidArgumentException('Hostname MikroTik tidak valid.');
        }

        return $host;
    }

    private function socketHost(string $host): string
    {
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '['.$host.']' : $host;
    }
}
