<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class SystemHealthService
{
    public function summary(): array
    {
        $checks = [
            'database' => $this->timed(function () {
                DB::select('select 1');
                return 'PostgreSQL reachable';
            }),
            'redis' => $this->timed(function () {
                $pong = Redis::connection()->ping();
                if ($pong === false) {
                    throw new \RuntimeException('Redis PING failed');
                }
                return 'Redis reachable';
            }),
            'storage' => $this->timed(function () {
                if (! is_writable(storage_path()) || ! is_writable(storage_path('framework'))) {
                    throw new \RuntimeException('Laravel storage is not writable');
                }
                return 'Storage writable';
            }),
        ];

        $checks['queue'] = $this->heartbeat('jaringanku:queue_heartbeat', 180, 'Queue worker heartbeat');
        $checks['scheduler'] = $this->heartbeat('jaringanku:scheduler_heartbeat', 180, 'Scheduler heartbeat');
        $checks['failed_jobs'] = $this->timed(function () {
            $count = DB::table('failed_jobs')->count();
            if ($count > 0) {
                return "{$count} failed job(s) require review";
            }
            return 'No failed jobs';
        }, false);

        $coreReady = collect(['database', 'redis', 'storage'])->every(fn ($key) => ($checks[$key]['ok'] ?? false) === true);
        $degraded = collect($checks)->contains(fn ($check) => ($check['ok'] ?? false) === false);

        return [
            'status' => $coreReady ? ($degraded ? 'degraded' : 'healthy') : 'unhealthy',
            'ready' => $coreReady,
            'checks' => $checks,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    public function readiness(): array
    {
        $summary = $this->summary();
        return [
            'status' => $summary['ready'] ? 'ready' : 'not_ready',
            'ready' => $summary['ready'],
            'checked_at' => $summary['checked_at'],
        ];
    }

    private function heartbeat(string $key, int $maxAgeSeconds, string $label): array
    {
        try {
            $value = Cache::get($key);
            if (! $value) {
                return ['ok' => false, 'message' => "{$label} belum tersedia", 'latency_ms' => null];
            }
            $at = \Carbon\CarbonImmutable::parse($value);
            $age = abs(now()->diffInSeconds($at));
            return [
                'ok' => $age <= $maxAgeSeconds,
                'message' => "{$label}: {$age}s ago",
                'latency_ms' => null,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'latency_ms' => null];
        }
    }

    private function timed(callable $callback, bool $failureOnWarning = true): array
    {
        $start = hrtime(true);
        try {
            $message = (string) $callback();
            $warning = str_contains($message, 'require review');
            return [
                'ok' => $failureOnWarning ? ! $warning : true,
                'message' => $message,
                'latency_ms' => round((hrtime(true) - $start) / 1_000_000, 2),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'latency_ms' => round((hrtime(true) - $start) / 1_000_000, 2),
            ];
        }
    }
}
