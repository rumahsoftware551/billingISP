<?php

namespace Tests\Feature;

use Tests\TestCase;

class RoleAwareUiSourceTest extends TestCase
{
    public function test_role_ui_profiles_cover_current_tenant_roles(): void
    {
        $source = file_get_contents(base_path('resources/js/config/roleUi.ts'));

        foreach (['owner','admin','finance','cs','noc','warehouse','viewer','technician'] as $role) {
            $this->assertStringContainsString($role.': {', $source);
        }
    }

    public function test_access_hook_exposes_normalized_role(): void
    {
        $source = file_get_contents(base_path('resources/js/hooks/useAccess.ts'));

        $this->assertStringContainsString('roleSlug', $source);
        $this->assertStringContainsString('roleName', $source);
        $this->assertStringContainsString('normalizeRole', $source);
    }

    public function test_dashboard_is_role_aware_and_does_not_use_one_generic_workspace(): void
    {
        $source = file_get_contents(base_path('resources/js/pages/Dashboard.tsx'));
        $profiles = file_get_contents(base_path('resources/js/config/roleUi.ts'));

        foreach ([
            'Dashboard Owner',
            'Dashboard Finance',
            'Dashboard Customer Service',
            'Dashboard NOC',
            'Dashboard Warehouse',
        ] as $needle) {
            $this->assertStringContainsString($needle, $profiles);
        }

        $this->assertStringContainsString('ROLE_UI[roleSlug]', $source);
        $this->assertStringContainsString('metricsFor(roleSlug', $source);
        $this->assertStringContainsString('Mode Read Only aktif', $source);
    }

    public function test_sidebar_keeps_permission_filter_and_admin_controls_are_role_limited(): void
    {
        $source = file_get_contents(base_path('resources/js/components/Layout.tsx'));
        $dashboard = file_get_contents(base_path('resources/js/pages/Dashboard.tsx'));

        $this->assertStringContainsString('.filter(item=>can(item.permission))', $source);
        $this->assertStringContainsString("systemAdmin && ['owner','admin'].includes(roleSlug)", $dashboard);
    }

    public function test_rbac_repair_command_exists(): void
    {
        $source = file_get_contents(base_path('app/Console/Commands/RbacRepairCommand.php'));

        $this->assertStringContainsString('jaringanku:rbac-repair', $source);
        $this->assertStringContainsString("'finance'", $source);
        $this->assertStringContainsString("'warehouse'", $source);
        $this->assertStringContainsString("'viewer'", $source);
    }
}
