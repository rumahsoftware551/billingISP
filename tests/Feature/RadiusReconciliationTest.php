<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RadiusReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_resync_rebuilds_active_and_suspended_service_projection(): void
    {
        $tenant = $this->createTenant();
        $plan = InternetPlan::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Home Test',
            'code' => 'HOME-TEST',
            'price' => 100000,
            'download_kbps' => 10000,
            'upload_kbps' => 5000,
            'active' => true,
            'radius_attributes' => ['Mikrotik-Rate-Limit' => '5M/10M'],
        ]);
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'customer_number' => 'RAD-0001',
            'name' => 'Radius Test',
            'status' => 'active',
        ]);

        $active = $this->service($tenant->id, $customer->id, $plan->id, 'RAD-ACTIVE', 'radius-active', 'active');
        $suspended = $this->service($tenant->id, $customer->id, $plan->id, 'RAD-SUSP', 'radius-suspended', 'suspended');

        DB::table('radcheck')->insert([
            'username' => $active->pppoe_username,
            'attribute' => 'Auth-Type',
            'op' => ':=',
            'value' => 'Reject',
        ]);

        $this->assertSame(0, Artisan::call('jaringanku:radius-resync'));
        $this->assertDatabaseHas('radcheck', [
            'username' => $active->pppoe_username,
            'attribute' => 'Cleartext-Password',
        ]);
        $this->assertDatabaseMissing('radcheck', [
            'username' => $active->pppoe_username,
            'attribute' => 'Auth-Type',
        ]);
        $this->assertDatabaseHas('radcheck', [
            'username' => $suspended->pppoe_username,
            'attribute' => 'Auth-Type',
            'value' => 'Reject',
        ]);
        $this->assertNotNull($active->fresh()->last_radius_sync_at);
        $this->assertNotNull($suspended->fresh()->last_radius_sync_at);
    }

    private function service(string $tenantId, int $customerId, int $planId, string $number, string $username, string $status): CustomerService
    {
        return CustomerService::query()->create([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'internet_plan_id' => $planId,
            'service_number' => $number,
            'service_type' => 'pppoe',
            'pppoe_username' => $username,
            'pppoe_password' => Str::random(32),
            'status' => $status,
            'billing_day' => 1,
            'due_day' => 10,
        ]);
    }
}
