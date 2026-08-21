<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantRouteBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_bound_model_from_current_tenant_is_accepted(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $customer = $this->customer($tenant, 'CUST-A');

        $this->registerBindingProbe();
        app()->forgetInstance(CurrentTenant::class);

        $this->actingAs($user)
            ->get('/_test/tenant-binding/'.$customer->id)
            ->assertOk()
            ->assertJsonPath('id', $customer->id);
    }

    public function test_bound_model_from_another_tenant_is_hidden(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $otherTenant = Tenant::query()->create([
            'name' => 'Other ISP',
            'slug' => 'other-isp',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);
        $foreignCustomer = $this->customer($otherTenant, 'CUST-B');

        $this->registerBindingProbe();
        app()->forgetInstance(CurrentTenant::class);

        $this->actingAs($user)
            ->get('/_test/tenant-binding/'.$foreignCustomer->id)
            ->assertNotFound();

        $this->assertDatabaseHas('customers', [
            'id' => $foreignCustomer->id,
            'tenant_id' => $otherTenant->id,
        ]);
        $this->assertNotSame($tenant->id, $otherTenant->id);
    }

    private function tenantUser(): array
    {
        $tenant = $this->createTenant('current-isp');
        $user = User::query()->create([
            'name' => 'Operator Test',
            'email' => 'operator-'.Str::lower(Str::random(10)).'@example.test',
            'password' => Hash::make(Str::password(24)),
            'status' => 'active',
            'is_platform_admin' => false,
        ]);

        DB::table('tenant_memberships')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role_id' => null,
            'is_default' => true,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$tenant, $user];
    }

    private function customer(Tenant $tenant, string $number): Customer
    {
        return Customer::query()->create([
            'tenant_id' => $tenant->id,
            'customer_number' => $number,
            'name' => 'Customer '.$number,
            'status' => 'active',
        ]);
    }

    private function registerBindingProbe(): void
    {
        Route::middleware(['web', 'auth', 'tenant', 'tenant.bound'])
            ->get('/_test/tenant-binding/{customer}', fn (Customer $customer) => response()->json([
                'id' => $customer->id,
            ]));
    }
}
