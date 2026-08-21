<?php

namespace App\Http\Middleware;

use App\Support\CurrentTenant;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBoundModelsBelongToTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app()->bound(CurrentTenant::class), 404);

        $tenantId = app(CurrentTenant::class)->id();

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if (! $parameter instanceof Model) {
                continue;
            }

            $attributes = $parameter->getAttributes();

            if (array_key_exists('tenant_id', $attributes)) {
                abort_unless((string) $attributes['tenant_id'] === $tenantId, 404);
            }
        }

        return $next($request);
    }
}
