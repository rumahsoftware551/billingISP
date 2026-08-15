<?php

namespace App\Http\Middleware;

use App\Services\BrandingService;
use App\Services\PermissionService;
use App\Support\CurrentTenant;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $tenant = app()->bound(CurrentTenant::class) ? app(CurrentTenant::class)->tenant : null;
        $portal = $request->attributes->get('portal_account');
        $partnerAccount = $request->attributes->get('partner_account');
        $inventoryAccount = $request->attributes->get('inventory_account');
        $access = $request->user() && $tenant ? app(PermissionService::class)->context((int)$request->user()->id) : ['role'=>null,'permissions'=>[]];
        $systemAdmin = (bool) ($request->user()?->is_platform_admin || in_array($access['role']['slug'] ?? null, ['owner','admin'], true));

        return [
            ...parent::share($request),
            'auth' => ['user' => $request->user() ? [...$request->user()->only('id', 'name', 'email'), 'is_platform_admin' => (bool) $request->user()->is_platform_admin] : null, 'system_admin' => $systemAdmin],
            'tenant' => $tenant?->only('id', 'name', 'slug'),
            'branding' => app(BrandingService::class)->forTenant($tenant),
            'access' => $access,
            'subscription' => $request->attributes->get('subscription_summary'),
            'release' => ['version' => config('jaringanku.version'), 'channel' => config('jaringanku.release_channel')],
            'portal' => $portal ? [
                'account' => $portal->only('id', 'email', 'status', 'must_change_password'),
                'customer' => $portal->customer?->only('id', 'customer_number', 'name', 'email', 'phone'),
            ] : null,
            'partner' => $partnerAccount ? [
                'account' => $partnerAccount->only('id','name','email','role','status','must_change_password'),
                'entity' => $partnerAccount->partner?->only('id','code','name','status','area_name'),
            ] : null,
            'inventory' => $inventoryAccount ? [
                'account' => $inventoryAccount->only('id','name','email','role','status','must_change_password','inventory_location_id','technician_id'),
                'location' => $inventoryAccount->location?->only('id','code','name','location_type'),
                'technician' => $inventoryAccount->technician?->only('id','code','name'),
            ] : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'radius_test' => fn () => $request->session()->get('radius_test'),
                'generated_webhook_secret' => fn () => $request->session()->get('generated_webhook_secret'),
                'generated_portal_password' => fn () => $request->session()->get('generated_portal_password'),
                'generated_user_password' => fn () => $request->session()->get('generated_user_password'),
            ],
        ];
    }
}
