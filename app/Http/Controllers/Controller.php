<?php

namespace App\Http\Controllers;

use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Model;

abstract class Controller
{
    protected function ensureTenantOwnership(Model $model): void
    {
        if (! array_key_exists('tenant_id', $model->getAttributes())) {
            return;
        }

        abort_unless(
            app()->bound(CurrentTenant::class) && (string) $model->getAttribute('tenant_id') === (string) app(CurrentTenant::class)->id(),
            404
        );
    }
}
