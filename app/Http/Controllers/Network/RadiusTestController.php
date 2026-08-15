<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Services\RadiusTestService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RadiusTestController extends Controller
{
    public function __invoke(Request $request, RadiusTestService $service)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:128'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $result = $service->authenticate($data['username'], $data['password']);

        return back()->with($result['ok'] ? 'success' : 'error', $result['ok'] ? 'RADIUS Access-Accept diterima.' : 'RADIUS test gagal.')
            ->with('radius_test', $result);
    }
}
