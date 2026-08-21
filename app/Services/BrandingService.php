<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantBranding;
use Illuminate\Support\Facades\Storage;

class BrandingService
{
    public function forTenant(?Tenant $tenant): array
    {
        $branding = $tenant ? TenantBranding::query()->where('tenant_id', $tenant->id)->first() : null;
        $asset = fn (?string $path) => $path ? Storage::disk('public')->url($path) : null;
        return [
            'app_name' => $branding?->app_name ?: 'Jaringanku',
            'company_name' => $branding?->company_name ?: $tenant?->name,
            'portal_title' => $branding?->portal_title ?: 'ISP Billing & Network Management',
            'primary_color' => $branding?->primary_color ?: '#0f6cbd',
            'accent_color' => $branding?->accent_color ?: '#16a34a',
            'logo_url' => $asset($branding?->logo_path),
            'login_logo_url' => $asset($branding?->login_logo_path) ?: $asset($branding?->logo_path),
            'favicon_url' => $asset($branding?->favicon_path),
            'invoice_logo_url' => $asset($branding?->invoice_logo_path) ?: $asset($branding?->logo_path),
            'support_phone' => $branding?->support_phone,
            'support_email' => $branding?->support_email,
            'address' => $branding?->address,
            'footer_text' => $branding?->footer_text ?: 'ISP Billing & Network Management System',
            'show_powered_by' => $branding ? (bool) $branding->show_powered_by : true,
        ];
    }
}
