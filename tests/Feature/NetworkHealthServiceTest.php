<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPlan;
use App\Models\NetworkNas;
use App\Models\Router;
use App\Models\Tenant;
use App\Services\NetworkHealthService;
use App\Services\RadiusProjectionService;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NetworkHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance(CurrentTenant::class);
        parent::tearDown();
    }

    public function test_snapshot_detects_stale_accounting_and_projection_drift(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Health Tenant', 'slug' => 'health-'.Str::lower(Str::random(6)),
            'status' => 'active', 'timezone' => 'Asia/Jakarta', 'currency' => 'IDR',
        ]);
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));

        $router = Router::query()->create([
            'name' => 'Router Test', 'host' => '192.0.2.10', 'rest_port' => 443,
            'api_username' => 'api-test', 'api_password' => 'Secret@123', 'verify_tls' => true,
        ]);
        $nas = NetworkNas::query()->create([
            'router_id' => $router->id, 'nasname' => '192.0.2.10', 'shortname' => 'router-test',
            'type' => 'mikrotik', 'secret' => 'RadiusSecret@123', 'coa_port' => 3799, 'active' => true,
        ]);
        app(RadiusProjectionService::class)->syncNas($nas);

        $customer = Customer::query()->create([
            'customer_number' => 'HLT-'.Str::upper(Str::random(8)), 'name' => 'Health Customer', 'status' => 'active',
        ]);
        $plan = InternetPlan::query()->create([
            'name' => 'Health Plan', 'code' => 'HP-'.Str::upper(Str::random(6)), 'price' => 200000,
            'download_kbps' => 20000, 'upload_kbps' => 10000, 'active' => true,
        ]);
        $service = CustomerService::query()->create([
            'customer_id' => $customer->id, 'internet_plan_id' => $plan->id,
            'router_id' => $router->id, 'network_nas_id' => $nas->id,
            'service_number' => 'HLT-SRV-'.Str::upper(Str::random(6)), 'service_type' => 'pppoe',
            'pppoe_username' => 'health-'.Str::lower(Str::random(8)), 'pppoe_password' => 'Test@12345',
            'status' => 'active', 'billing_day' => 1, 'due_day' => 10,
        ]);
        app(RadiusProjectionService::class)->syncService($service);

        DB::table('radacct')->insert([
            'acctsessionid' => 'fresh-1', 'acctuniqueid' => 'fresh-'.Str::uuid(),
            'username' => $service->pppoe_username, 'nasipaddress' => $nas->nasname,
            'acctstarttime' => now()->subMinutes(5), 'acctupdatetime' => now()->subMinute(),
        ]);
        DB::table('radacct')->insert([
            'acctsessionid' => 'stale-1', 'acctuniqueid' => 'stale-'.Str::uuid(),
            'username' => $service->pppoe_username, 'nasipaddress' => $nas->nasname,
            'acctstarttime' => now()->subHour(), 'acctupdatetime' => now()->subMinutes(30),
        ]);

        $snapshot = app(NetworkHealthService::class)->snapshot($tenant, false);
        $this->assertSame(0, $snapshot['nas_projection_mismatch']);
        $this->assertSame(0, $snapshot['active_projection_drift']);
        $this->assertSame(2, $snapshot['online_sessions']);
        $this->assertSame(1, $snapshot['stale_sessions']);

        DB::table('radcheck')->where('username', $service->pppoe_username)->delete();
        $drifted = app(NetworkHealthService::class)->snapshot($tenant, false);
        $this->assertSame(1, $drifted['active_projection_drift']);
    }
}
