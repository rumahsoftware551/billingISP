<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\InternetPlan;
use App\Models\IpPool;
use App\Models\NetworkNas;
use App\Models\Router;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class NetworkController extends Controller
{
    public function __invoke()
    {
        return Inertia::render('Network/Index', [
            'routers' => Router::query()->latest()->get(),
            'nas' => NetworkNas::query()->with('router:id,name')->latest()->get(),
            'plans' => InternetPlan::query()->orderBy('name')->get(),
            'pools' => IpPool::query()->orderBy('name')->get(),
            'radius' => [
                'test_ready' => DB::table('radcheck')->where('username', 'phase2-test')->where('attribute', 'Cleartext-Password')->exists(),
            ],
        ]);
    }
}
