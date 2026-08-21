<?php

use App\Http\Middleware\EnsureTenant;
use App\Http\Middleware\EnsureBoundModelsBelongToTenant;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\EnsureSystemAdmin;
use App\Http\Middleware\EnsureCustomerPortal;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Middleware\EnforcePlanLimit;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsurePartnerPortal;
use App\Http\Middleware\EnsureInventoryPortal;
use App\Http\Middleware\RequirePermission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [HandleInertiaRequests::class, SecurityHeaders::class]);
        $middleware->alias(['tenant' => EnsureTenant::class, 'tenant.bound' => EnsureBoundModelsBelongToTenant::class, 'system-admin' => EnsureSystemAdmin::class, 'portal.auth' => EnsureCustomerPortal::class, 'subscription' => EnsureActiveSubscription::class, 'subscription.limit' => EnforcePlanLimit::class, 'platform-admin' => EnsurePlatformAdmin::class, 'partner.auth' => EnsurePartnerPortal::class, 'inventory.auth' => EnsureInventoryPortal::class, 'permission' => RequirePermission::class]);

        $trusted = trim((string) env('TRUSTED_PROXIES', ''));
        if ($trusted !== '') {
            $middleware->trustProxies(at: $trusted === '*'
                ? '*'
                : array_values(array_filter(array_map('trim', explode(',', $trusted))))
            );
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {})
    ->create();
