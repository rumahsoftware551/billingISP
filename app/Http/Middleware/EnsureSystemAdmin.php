<?php

namespace App\Http\Middleware;

use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureSystemAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        if ($user->is_platform_admin) {
            return $next($request);
        }

        $tenantId = app(CurrentTenant::class)->id();
        $allowed = DB::table('tenant_memberships')
            ->join('roles', 'roles.id', '=', 'tenant_memberships.role_id')
            ->where('tenant_memberships.tenant_id', $tenantId)
            ->where('tenant_memberships.user_id', $user->id)
            ->whereIn('roles.slug', ['owner', 'admin'])
            ->exists();

        abort_unless($allowed, 403, 'System settings hanya untuk owner/admin.');
        return $next($request);
    }
}
