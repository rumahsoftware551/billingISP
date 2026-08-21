<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        abort_unless($request->user(), 401);
        if ($request->user()->is_platform_admin) return $next($request);
        abort_unless(app(PermissionService::class)->allows((int)$request->user()->id, $permission), 403, 'Anda tidak memiliki izin untuk fitur ini.');
        return $next($request);
    }
}
