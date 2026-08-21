<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_application_reports_liveness(): void
    {
        $this->get('/up')->assertOk();
    }
}
