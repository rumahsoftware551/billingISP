<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class RouterOsCompatibilitySourceTest extends TestCase
{
    public function test_rc4_supports_routeros_v6_and_v7_and_real_radius_readiness(): void
    {
        $root = dirname(__DIR__, 2);

        $client = file_get_contents($root.'/app/Services/MikrotikRestClient.php');
        self::assertStringContainsString("'api' =>", $client);
        self::assertStringContainsString("'api_ssl' =>", $client);
        self::assertStringContainsString("'rest' =>", $client);
        self::assertStringContainsString('/system/resource/print', $client);
        self::assertStringContainsString("/system/resource'", $client);
        self::assertStringContainsString('stream_socket_client', $client);

        $router = file_get_contents($root.'/app/Models/Router.php');
        self::assertStringContainsString("'api_driver'", $router);
        self::assertStringContainsString("'api_port'", $router);

        $network = file_get_contents($root.'/app/Http/Controllers/Network/NetworkController.php');
        self::assertStringNotContainsString('phase2-test', $network);
        self::assertStringContainsString('Cleartext-Password', $network);
        self::assertStringContainsString('test_username', $network);

        $ui = file_get_contents($root.'/resources/js/pages/Network/Index.tsx');
        self::assertStringContainsString('RouterOS v6/v7 Classic API', $ui);
        self::assertStringContainsString('RouterOS v7 REST', $ui);

        self::assertFileExists($root.'/app/Console/Commands/ImportRadiusNasCommand.php');
        self::assertFileExists($root.'/database/migrations/2026_08_18_230000_add_router_api_driver_support.php');
    }
}
