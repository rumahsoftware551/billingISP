<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPlan;
use App\Models\NetworkNas;
use App\Models\Tenant;
use App\Services\RadiusProjectionService;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NetworkProjectionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance(CurrentTenant::class);
        parent::tearDown();
    }

    public function test_active_suspend_reactivate_radius_projection_is_deterministic(): void
    {
        [$tenant, $service] = $this->serviceFixture('projection');
        $projection = app(RadiusProjectionService::class);

        $projection->syncService($service);
        $this->assertDatabaseHas('radcheck', [
            'username' => $service->pppoe_username,
            'attribute' => 'Cleartext-Password',
            'op' => ':=',
        ]);
        $this->assertFalse(DB::table('radcheck')->where('username', $service->pppoe_username)->where('attribute', 'Auth-Type')->exists());
        $this->assertDatabaseHas('radreply', [
            'username' => $service->pppoe_username,
            'attribute' => 'Acct-Interim-Interval',
        ]);

        $service->forceFill(['status' => 'suspended'])->save();
        $projection->syncService($service->fresh());
        $this->assertDatabaseHas('radcheck', [
            'username' => $service->pppoe_username,
            'attribute' => 'Auth-Type',
            'value' => 'Reject',
        ]);
        $this->assertSame(1, DB::table('radcheck')->where('username', $service->pppoe_username)->count());
        $this->assertFalse(DB::table('radreply')->where('username', $service->pppoe_username)->exists());
        $this->assertFalse(DB::table('radusergroup')->where('username', $service->pppoe_username)->exists());

        $service->forceFill(['status' => 'active'])->save();
        $projection->syncService($service->fresh());
        $this->assertDatabaseHas('radcheck', [
            'username' => $service->pppoe_username,
            'attribute' => 'Cleartext-Password',
        ]);
        $this->assertFalse(DB::table('radcheck')->where('username', $service->pppoe_username)->where('attribute', 'Auth-Type')->exists());
        $this->assertSame($tenant->id, $service->tenant_id);
    }

    public function test_nas_projection_uses_encrypted_source_of_truth_and_plain_radius_projection(): void
    {
        $tenant = $this->tenant('nas');
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));

        $nas = NetworkNas::query()->create([
            'nasname' => '192.0.2.'.random_int(10, 200),
            'shortname' => 'nas-'.Str::lower(Str::random(6)),
            'type' => 'mikrotik',
            'secret' => 'NetworkSecret!'.Str::random(8),
            'coa_port' => 3799,
            'active' => true,
        ]);

        app(RadiusProjectionService::class)->syncNas($nas);

        $row = DB::table('nas')->where('nasname', $nas->nasname)->first();
        $this->assertNotNull($row);
        $this->assertSame($nas->secret, $row->secret);
        $this->assertNotSame($nas->secret, $nas->getRawOriginal('secret_encrypted'));
    }

    /** @return array{0:Tenant,1:CustomerService} */
    private function serviceFixture(string $suffix): array
    {
        $tenant = $this->tenant($suffix);
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));

        $customer = Customer::query()->create([
            'customer_number' => 'NET-'.Str::upper(Str::random(8)),
            'name' => 'Network Customer',
            'status' => 'active',
        ]);
        $plan = InternetPlan::query()->create([
            'name' => 'Network Plan',
            'code' => 'NP-'.Str::upper(Str::random(6)),
            'price' => 250000,
            'download_kbps' => 50000,
            'upload_kbps' => 25000,
            'active' => true,
            'acct_interim_interval' => 300,
            'radius_attributes' => ['Mikrotik-Rate-Limit' => '25M/50M'],
        ]);
        $service = CustomerService::query()->create([
            'customer_id' => $customer->id,
            'internet_plan_id' => $plan->id,
            'service_number' => 'SRV-'.Str::upper(Str::random(8)),
            'service_type' => 'pppoe',
            'pppoe_username' => 'net-'.Str::lower(Str::random(10)),
            'pppoe_password' => 'Test@12345',
            'status' => 'active',
            'billing_day' => 1,
            'due_day' => 10,
            'installed_at' => now()->subDay(),
        ]);

        return [$tenant, $service];
    }

    private function tenant(string $suffix): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Network Tenant '.$suffix,
            'slug' => 'net-'.$suffix.'-'.Str::lower(Str::random(6)),
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);
    }
}
