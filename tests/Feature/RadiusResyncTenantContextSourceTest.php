<?php

namespace Tests\Feature;

use Tests\TestCase;

class RadiusResyncTenantContextSourceTest extends TestCase
{
    public function test_radius_resync_binds_current_tenant_for_cli_execution(): void
    {
        $source = (string) file_get_contents(base_path('routes/console.php'));

        $this->assertStringContainsString(
            "jaringanku:radius-resync {--tenant= : Optional tenant slug}",
            $source
        );

        $this->assertStringContainsString(
            "\\App\\Support\\CurrentTenant::class",
            $source
        );

        $this->assertStringContainsString(
            "new \\App\\Support\\CurrentTenant(\$tenant)",
            $source
        );

        $this->assertStringContainsString(
            "app()->forgetInstance(\\App\\Support\\CurrentTenant::class)",
            $source
        );
    }
}