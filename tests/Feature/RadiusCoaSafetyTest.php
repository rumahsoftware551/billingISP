<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPlan;
use App\Models\NetworkNas;
use App\Models\Radacct;
use App\Models\Tenant;
use App\Services\RadiusCoaService;
use App\Services\RadiusPacketClient;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class RadiusCoaSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance(CurrentTenant::class);
        Mockery::close();
        parent::tearDown();
    }

    public function test_disconnect_ack_is_audited_and_closed_session_is_rejected(): void
    {
        [$tenant, $service, $nas] = $this->fixture();
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));

        $session = Radacct::query()->create([
            'acctsessionid' => 'coa-session',
            'acctuniqueid' => 'coa-'.Str::uuid(),
            'username' => $service->pppoe_username,
            'nasipaddress' => $nas->nasname,
            'acctstarttime' => now()->subMinutes(3),
            'acctupdatetime' => now(),
            'framedipaddress' => '198.51.100.20',
        ]);

        $client = Mockery::mock(RadiusPacketClient::class);
        $client->shouldReceive('sendLines')->once()->andReturn([
            'ok' => true,
            'exit_code' => 0,
            'response_code' => 'Disconnect-ACK',
            'output' => 'Received Disconnect-ACK',
            'target' => $nas->nasname.':3799',
            'type' => 'disconnect',
        ]);

        $result = (new RadiusCoaService($client))->disconnectSession($session);
        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('radius_action_logs', [
            'tenant_id' => $tenant->id,
            'customer_service_id' => $service->id,
            'radacctid' => $session->getKey(),
            'action' => 'disconnect',
            'response_code' => 'Disconnect-ACK',
            'success' => true,
        ]);

        $session->forceFill(['acctstoptime' => now()])->save();
        $this->expectException(RuntimeException::class);
        (new RadiusCoaService($client))->disconnectSession($session->fresh());
    }

    /** @return array{0:Tenant,1:CustomerService,2:NetworkNas} */
    private function fixture(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'CoA Tenant', 'slug' => 'coa-'.Str::lower(Str::random(6)),
            'status' => 'active', 'timezone' => 'Asia/Jakarta', 'currency' => 'IDR',
        ]);
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));
        $nas = NetworkNas::query()->create([
            'nasname' => '192.0.2.55', 'shortname' => 'coa-test', 'type' => 'mikrotik',
            'secret' => 'RadiusSecret@123', 'coa_port' => 3799, 'active' => true,
        ]);
        $customer = Customer::query()->create([
            'customer_number' => 'COA-'.Str::upper(Str::random(6)), 'name' => 'CoA Customer', 'status' => 'active',
        ]);
        $plan = InternetPlan::query()->create([
            'name' => 'CoA Plan', 'code' => 'COA-'.Str::upper(Str::random(5)), 'price' => 200000,
            'download_kbps' => 20000, 'upload_kbps' => 10000, 'active' => true,
            'radius_attributes' => ['Mikrotik-Rate-Limit' => '10M/20M'],
        ]);
        $service = CustomerService::query()->create([
            'customer_id' => $customer->id, 'internet_plan_id' => $plan->id, 'network_nas_id' => $nas->id,
            'service_number' => 'COA-SRV-'.Str::upper(Str::random(6)), 'service_type' => 'pppoe',
            'pppoe_username' => 'coa-'.Str::lower(Str::random(8)), 'pppoe_password' => 'Test@12345',
            'status' => 'active', 'billing_day' => 1, 'due_day' => 10,
        ]);

        return [$tenant, $service, $nas];
    }
}
