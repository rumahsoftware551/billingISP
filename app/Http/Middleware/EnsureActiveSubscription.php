<?php
namespace App\Http\Middleware;

use App\Services\SaasPlanService;
use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app(CurrentTenant::class)->tenant;
        $summary = app(SaasPlanService::class)->summary($tenant);
        $request->attributes->set('subscription_summary', $summary);
        abort_unless($summary['usable'], 402, 'Subscription tenant tidak aktif. Hubungi administrator platform.');
        return $next($request);
    }
}
