<?php

namespace App\Services;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class RadiusTestService
{
    public function authenticate(string $username, string $password): array
    {
        $secret = (string) config('services.radius.shared_secret');
        $host = (string) config('services.radius.host', 'radius');

        $process = new Process([
            'radtest', $username, $password, $host, '0', $secret,
        ]);
        $process->setTimeout(20);

        $timedOut = false;
        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            $timedOut = true;
        }

        $output = trim($process->getOutput()."\n".$process->getErrorOutput());
        if ($timedOut) {
            $output = trim($output."\nRADIUS test timed out waiting for a response.");
        }

        $accepted = str_contains($output, 'Access-Accept');
        $rejected = str_contains($output, 'Access-Reject');

        return [
            // Backward compatible: existing callers use ok to mean Access-Accept.
            'ok' => $accepted,
            'accepted' => $accepted,
            'rejected' => $rejected,
            'timed_out' => $timedOut,
            'exit_code' => $process->getExitCode(),
            'output' => $output,
        ];
    }
}
