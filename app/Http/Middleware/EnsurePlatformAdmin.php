<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user(), 401);
        abort_unless((bool) $request->user()->is_platform_admin, 403, 'Akses khusus Platform Super Admin.');
        return $next($request);
    }
}
