<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class ProductionSecretAuthoritySourceTest extends TestCase
{
    public function test_production_uses_file_secrets_and_deterministic_network_health_config(): void
    {
        $root = dirname(__DIR__, 2);

        $database = file_get_contents($root.'/config/database.php');

        self::assertStringContainsString('DB_PASSWORD_FILE', $database);
        self::assertStringContainsString('/run/secrets/db_password', $database);

        $radiusSources = '';

        foreach (glob($root.'/config/*.php') as $file) {
            $source = file_get_contents($file);

            if (str_contains($source, 'RADIUS_SHARED_SECRET')) {
                $radiusSources .= $source;
            }
        }

        self::assertStringContainsString('RADIUS_SHARED_SECRET_FILE', $radiusSources);
        self::assertStringContainsString('/run/secrets/radius_shared_secret', $radiusSources);

        $compose = file_get_contents($root.'/docker-compose.prod.yml');

        self::assertStringContainsString('RADIUS_CLIENT_NETWORK', $compose);
        self::assertStringContainsString('nginx -t', $compose);
    }
}
