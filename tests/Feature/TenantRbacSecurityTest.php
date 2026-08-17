<?php

namespace Tests\Feature;

use App\Http\Middleware\RequirePermission;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantRbacSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(
            RequirePermission::class.':customers.view'
        )->get(
            '/__tests/permission/customers-view',
            fn () => response()->json(['ok' => true])
        );
    }

    protected function tearDown(): void
    {
        app()->forgetInstance(CurrentTenant::class);

        parent::tearDown();
    }

    public function test_tenant_global_scope_prevents_cross_tenant_reads(): void
    {
        $tenantA = $this->createTenant('a');
        $tenantB = $this->createTenant('b');

        $this->bindTenant($tenantA);

        $created = TenantScopedSetting::query()->create([
            'key' => 'tenant-a-setting',
            'value' => json_encode(['source' => 'tenant-a']),
        ]);

        DB::table('tenant_settings')->insert([
            'tenant_id' => $tenantB->id,
            'key' => 'tenant-b-setting',
            'value' => json_encode(['source' => 'tenant-b']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame($tenantA->id, $created->tenant_id);

        $this->assertSame(
            1,
            TenantScopedSetting::query()->count()
        );

        $this->assertSame(
            $tenantA->id,
            TenantScopedSetting::query()->firstOrFail()->tenant_id
        );

        $this->assertSame(
            2,
            TenantScopedSetting::withoutGlobalScopes()->count()
        );
    }

    public function test_active_user_with_permission_is_allowed(): void
    {
        $tenant = $this->createTenant('allowed');
        $user = $this->createUser('allowed');
        $role = $this->createRole($tenant, 'finance');

        $this->createMembership(
            tenant: $tenant,
            user: $user,
            role: $role,
            status: 'active'
        );

        $this->grantPermission($role, 'customers.view');

        $this->bindTenant($tenant);

        $this->actingAs($user)
            ->get('/__tests/permission/customers-view')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_user_without_permission_receives_403(): void
    {
        $tenant = $this->createTenant('denied');
        $user = $this->createUser('denied');
        $role = $this->createRole($tenant, 'viewer');

        $this->createMembership(
            tenant: $tenant,
            user: $user,
            role: $role,
            status: 'active'
        );

        $this->bindTenant($tenant);

        $this->actingAs($user)
            ->get('/__tests/permission/customers-view')
            ->assertForbidden();
    }

    public function test_inactive_membership_receives_403(): void
    {
        $tenant = $this->createTenant('inactive');
        $user = $this->createUser('inactive');
        $role = $this->createRole($tenant, 'staff');

        $this->createMembership(
            tenant: $tenant,
            user: $user,
            role: $role,
            status: 'inactive'
        );

        $this->grantPermission($role, 'customers.view');

        $this->bindTenant($tenant);

        $this->actingAs($user)
            ->get('/__tests/permission/customers-view')
            ->assertForbidden();
    }

    public function test_platform_admin_bypasses_tenant_permission(): void
    {
        $user = $this->createUser(
            'platform-admin',
            true
        );

        $this->actingAs($user)
            ->get('/__tests/permission/customers-view')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_permission_from_tenant_a_does_not_leak_to_tenant_b(): void
    {
        $tenantA = $this->createTenant('scope-a');
        $tenantB = $this->createTenant('scope-b');

        $user = $this->createUser('multi-tenant');

        $roleA = $this->createRole($tenantA, 'operator-a');
        $roleB = $this->createRole($tenantB, 'operator-b');

        $this->createMembership(
            tenant: $tenantA,
            user: $user,
            role: $roleA,
            status: 'active',
            isDefault: true
        );

        $this->createMembership(
            tenant: $tenantB,
            user: $user,
            role: $roleB,
            status: 'active',
            isDefault: false
        );

        $this->grantPermission(
            $roleA,
            'customers.view'
        );

        $this->bindTenant($tenantB);

        $this->actingAs($user)
            ->get('/__tests/permission/customers-view')
            ->assertForbidden();
    }

    private function createTenant(string $suffix): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Tenant '.Str::upper($suffix),
            'slug' => 'tenant-'.$suffix.'-'.Str::lower(Str::random(6)),
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);
    }

    private function createUser(
        string $suffix,
        bool $platformAdmin = false
    ): User {
        return User::query()->create([
            'name' => 'User '.$suffix,
            'email' => $suffix.'-'.Str::lower(Str::random(6)).'@example.test',
            'password' => 'Test@12345',
            'is_platform_admin' => $platformAdmin,
            'status' => 'active',
        ]);
    }

    private function createRole(
        Tenant $tenant,
        string $slug
    ): Role {
        return Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => Str::headline($slug),
            'slug' => $slug,
        ]);
    }

    private function createMembership(
        Tenant $tenant,
        User $user,
        Role $role,
        string $status = 'active',
        bool $isDefault = true
    ): void {
        DB::table('tenant_memberships')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'is_default' => $isDefault,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function grantPermission(
        Role $role,
        string $slug
    ): Permission {
        $permission = Permission::query()->create([
            'name' => Str::headline(
                str_replace('.', ' ', $slug)
            ),
            'slug' => $slug,
        ]);

        DB::table('permission_role')->insert([
            'permission_id' => $permission->id,
            'role_id' => $role->id,
        ]);

        return $permission;
    }

    private function bindTenant(Tenant $tenant): void
    {
        app()->instance(
            CurrentTenant::class,
            new CurrentTenant($tenant)
        );
    }
}

class TenantScopedSetting extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_settings';

    protected $guarded = [];
}