<?php

namespace Tests\Feature;

use App\Services\SystemHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class SystemHealthReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_requires_queue_and_scheduler_heartbeats(): void
    {
        Redis::shouldReceive('connection->ping')->andReturn(true);
        Cache::put('jaringanku:queue_heartbeat', now()->toIso8601String(), now()->addMinutes(5));

        $summary = app(SystemHealthService::class)->summary();

        $this->assertFalse($summary['ready']);
        $this->assertTrue($summary['checks']['queue']['ok']);
        $this->assertFalse($summary['checks']['scheduler']['ok']);
    }
}
