<?php
namespace App\Http\Middleware;

use App\Services\SaasPlanService;
use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;

class EnforcePlanLimit
{
    public function handle(Request $request, Closure $next, string $resource): Response
    {
        $tenant = app(CurrentTenant::class)->tenant;
        return DB::transaction(function () use ($request, $next, $resource, $tenant) {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ["jaringanku:quota:{$tenant->id}:{$resource}"]);
            app(SaasPlanService::class)->assertCanCreate($tenant, $resource);
            return $next($request);
        });
    }
}
