<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\InternetPlan;
use App\Models\IpPool;
use App\Models\NetworkNas;
use App\Models\Router;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class NetworkController extends Controller
{
    public function __invoke()
    {
        $tenantId = app(CurrentTenant::class)->id();

        $projectedService = DB::table('customer_services as s')
            ->join('radcheck as r', function ($join) {
                $join->on('r.username', '=', 's.pppoe_username')
                    ->where('r.attribute', '=', 'Cleartext-Password')
                    ->where('r.op', '=', ':=');
            })
            ->where('s.tenant_id', $tenantId)
            ->where('s.status', 'active')
            ->whereNull('s.deleted_at')
            ->orderBy('s.id')
            ->select('s.pppoe_username')
            ->first();

        return Inertia::render('Network/Index', [
            'routers' => Router::query()->latest()->get(),
            'nas' => NetworkNas::query()->with('router:id,name')->latest()->get(),
            'plans' => InternetPlan::query()->orderBy('name')->get(),
            'pools' => IpPool::query()->orderBy('name')->get(),
            'radius' => [
                'test_ready' => $projectedService !== null,
                'test_username' => $projectedService?->pppoe_username,
            ],
        ]);
    }
}
