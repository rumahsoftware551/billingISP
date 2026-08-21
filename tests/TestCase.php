<?php

namespace Tests;

use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function createTenant(string $slug = 'test-isp'): Tenant
    {
        $tenant = Tenant::query()->create([
            'name' => 'Test ISP',
            'slug' => $slug,
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));

        return $tenant;
    }
}
