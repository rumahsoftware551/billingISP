<?php

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

class RadiusPacketClient
{
    /**
     * Send a RADIUS packet with radclient without invoking a shell.
     * The shared secret is written to a 0600 temporary file and supplied via -S.
     *
     * @param  array<int,string>  $lines
     * @return array{ok:bool,exit_code:int,response_code:?string,output:string,target:string,type:string}
     */
    public function sendLines(
        string $server,
        int $port,
        string $type,
        string $secret,
        array $lines,
        int $timeout = 3,
        int $retries = 2,
    ): array {
        $type = strtolower(trim($type));
        if (! in_array($type, ['auth', 'acct', 'status', 'coa', 'disconnect'], true)) {
            throw new InvalidArgumentException('Tipe paket RADIUS tidak didukung: '.$type);
        }
        if ($server === '' || $port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Target RADIUS tidak valid.');
        }
        if ($secret === '') {
            throw new InvalidArgumentException('Shared secret RADIUS kosong.');
        }

        $secretFile = tempnam(sys_get_temp_dir(), 'jrg-radius-secret-');
        if ($secretFile === false) {
            throw new RuntimeException('Gagal membuat temporary secret file untuk radclient.');
        }

        try {
            file_put_contents($secretFile, $secret."\n", LOCK_EX);
            @chmod($secretFile, 0600);

            $target = $this->formatTarget($server, $port);
            $command = [
                'radclient', '-x', '-t', (string) max(1, $timeout), '-r', (string) max(1, $retries),
                '-S', $secretFile, $target, $type,
            ];

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open($command, $descriptors, $pipes);
            if (! is_resource($process)) {
                throw new RuntimeException('Gagal menjalankan radclient.');
            }

            fwrite($pipes[0], implode("\n", $lines)."\n");
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]) ?: '';
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]) ?: '';
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            $output = trim($stdout."\n".$stderr);
            preg_match('/\b(Access-Accept|Access-Reject|Accounting-Response|Disconnect-ACK|Disconnect-NAK|CoA-ACK|CoA-NAK|Protocol-Error)\b/', $output, $match);
            $responseCode = $match[1] ?? null;

            return [
                'ok' => $exitCode === 0 && ! in_array($responseCode, ['Access-Reject', 'Disconnect-NAK', 'CoA-NAK', 'Protocol-Error'], true),
                'exit_code' => $exitCode,
                'response_code' => $responseCode,
                'output' => $output,
                'target' => $target,
                'type' => $type,
            ];
        } finally {
            @unlink($secretFile);
        }
    }

    private function formatTarget(string $server, int $port): string
    {
        $server = trim($server);
        if (str_contains($server, ':') && ! str_starts_with($server, '[')) {
            return sprintf('[%s]:%d', $server, $port);
        }

        return sprintf('%s:%d', $server, $port);
    }
}
