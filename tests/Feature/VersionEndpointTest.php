<?php

namespace Tests\Feature;

use Tests\TestCase;

class VersionEndpointTest extends TestCase
{
    public function test_version_endpoint_returns_product_information(): void
    {
        $response = $this->getJson('/version');

        $response
            ->assertOk()
            ->assertJsonPath('product', 'Jaringanku')
            ->assertJsonStructure([
                'product',
                'version',
                'channel',
            ]);
    }
}