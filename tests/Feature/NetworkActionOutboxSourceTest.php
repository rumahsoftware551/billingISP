<?php

namespace Tests\Feature;

use Tests\TestCase;

class NetworkActionOutboxSourceTest extends TestCase
{
    public function test_disconnect_is_deferred_and_retried_after_commit(): void
    {
        $service = file_get_contents(base_path('app/Services/NetworkActionOutboxService.php'));
        $job = file_get_contents(base_path('app/Jobs/ProcessNetworkActionJob.php'));
        $migration = file_get_contents(base_path('database/migrations/2026_08_21_160000_create_network_action_outbox_table.php'));

        self::assertStringContainsString('network_action_outbox', $migration);
        self::assertStringContainsString('network_action_outbox_idempotency_unique', $migration);
        self::assertStringContainsString('afterCommit()', $service);
        self::assertStringContainsString('$service->status !== \'suspended\'', $job);
        self::assertStringContainsString("'status' => 'pending'", $job);
        self::assertStringContainsString("'status' => 'failed'", $job);
        self::assertStringContainsString('public function failed', $job);
    }
}
