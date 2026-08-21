<?php

namespace App\Http\Controllers\Commercial;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

class AccessController extends Controller
{
    public function __invoke(): Response
    {
        $tenants = app()->environment('local')
            ? Tenant::query()->where('status','active')->orderBy('name')->get(['name','slug'])
            : collect();
        return Inertia::render('Access', [
            'tenants' => $tenants,
            'defaultTenantSlug' => app()->environment('local') ? (string) config('jaringanku.seed_tenant_slug', 'demo-isp') : '',
        ]);
    }
}
