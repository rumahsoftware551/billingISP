<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class OperationalSafetySourceTest extends TestCase
{
    public function test_production_migrations_are_explicit_and_storage_is_backed_up(): void
    {
        $root = dirname(__DIR__, 2);
        $compose = file_get_contents($root.'/docker-compose.prod.yml');
        $backup = file_get_contents($root.'/docker/backup/backup-loop.sh');
        $deploy = file_get_contents($root.'/scripts/prod-up.sh');

        self::assertStringContainsString('RUN_MIGRATIONS: "false"', $compose);
        self::assertStringContainsString('migrate:', $compose);
        self::assertStringContainsString('php artisan migrate --force --isolated', $compose);
        self::assertStringContainsString('- storage:/storage:ro', $compose);
        self::assertStringContainsString('.storage.tar.gz', $backup);
        self::assertStringContainsString('manifest.sha256', $backup);
        self::assertStringContainsString('prod-backup.sh', $deploy);
        self::assertStringContainsString('run --rm --no-deps migrate', $deploy);
    }

    public function test_purchase_receive_guard_is_present(): void
    {
        $root = dirname(__DIR__, 2);
        $purchase = file_get_contents($root.'/app/Http/Controllers/Inventory/InventoryPurchaseController.php');

        self::assertStringContainsString('lockForUpdate()', $purchase);
    }
}
