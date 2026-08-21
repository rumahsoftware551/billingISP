<?php

namespace Tests\Feature;

use Tests\TestCase;

class UiRegressionSourceTest extends TestCase
{
    public function test_professional_ui_foundation_files_are_present(): void
    {
        foreach ([
            'resources/js/components/Ui.tsx',
            'resources/js/components/AuthShell.tsx',
            'resources/js/hooks/useAccess.ts',
            'resources/js/components/Layout.tsx',
        ] as $path) {
            $this->assertFileExists(base_path($path), $path.' must exist');
        }
    }

    public function test_permission_aware_pages_keep_frontend_access_guards(): void
    {
        $pages = [
            'resources/js/pages/Dashboard.tsx' => ['useAccess', 'customers.manage'],
            'resources/js/pages/Customers/Index.tsx' => ['useAccess', 'customers.manage'],
            'resources/js/pages/Customers/Show.tsx' => ['useAccess'],
            'resources/js/pages/Billing/Index.tsx' => ['useAccess'],
            'resources/js/pages/Network/Index.tsx' => ['useAccess', 'network.manage'],
            'resources/js/pages/Sessions/Index.tsx' => ['useAccess', 'network.manage'],
            'resources/js/pages/Partners/Index.tsx' => ['useAccess', 'partners.manage'],
            'resources/js/pages/InventoryAdmin/Index.tsx' => ['useAccess', 'inventory.manage'],
            'resources/js/pages/FieldOps/Index.tsx' => ['useAccess', 'field_ops.manage'],
        ];

        foreach ($pages as $path => $needles) {
            $source = file_get_contents(base_path($path));
            $this->assertIsString($source);
            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $source, $path.' must contain '.$needle);
            }
        }
    }

    public function test_login_pages_do_not_expose_default_demo_credentials(): void
    {
        $paths = [
            'resources/js/pages/Auth/Login.tsx',
            'resources/js/pages/Portal/Auth/Login.tsx',
            'resources/js/pages/Partner/Auth/Login.tsx',
            'resources/js/pages/Inventory/Auth/Login.tsx',
            'resources/js/pages/Access.tsx',
        ];

        $forbidden = [
            'Admin@12345',
            'Demo@12345',
            'admin@jaringanku.local',
            'finance@jaringanku.local',
            'viewer@jaringanku.local',
        ];

        foreach ($paths as $path) {
            $source = file_get_contents(base_path($path));
            $this->assertIsString($source);
            foreach ($forbidden as $credential) {
                $this->assertStringNotContainsString($credential, $source, $path.' exposes a demo credential');
            }
        }
    }

    public function test_phase16_global_legacy_css_compatibility_layer_is_removed(): void
    {
        $css = file_get_contents(base_path('resources/css/app.css'));

        $this->assertIsString($css);
        $this->assertStringNotContainsString('Phase 16 visual compatibility', $css);
        $this->assertStringNotContainsString('Portal compatibility for cumulative Phase 10-14 pages', $css);
        $this->assertStringContainsString('Phase 02 professional UI foundation', $css);
        $this->assertStringContainsString('Phase 02.7 responsive polish', $css);
    }

    public function test_access_center_exposes_all_supported_portal_types(): void
    {
        $source = file_get_contents(base_path('resources/js/pages/Access.tsx'));

        $this->assertStringContainsString('Admin ISP', $source);
        $this->assertStringContainsString('Customer Portal', $source);
        $this->assertStringContainsString('Portal Mitra', $source);
        $this->assertStringContainsString('Portal Inventory', $source);
    }
}
