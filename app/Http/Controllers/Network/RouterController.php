<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\Router;
use App\Services\MikrotikRestClient;
use App\Services\RouterEndpointPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class RouterController extends Controller
{
    public function store(Request $request, RouterEndpointPolicy $endpointPolicy): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'host' => ['required', 'string', 'max:255'],
            'rest_port' => ['required', 'integer', 'between:1,65535'],
            'api_username' => ['required', 'string', 'max:120'],
            'api_password' => ['required', 'string', 'max:255'],
            'verify_tls' => ['required', 'boolean'],
        ]);

        $endpointPolicy->validateOrFail($data['host'], (int) $data['rest_port'], (bool) $data['verify_tls']);
        Router::create($data);

        return back()->with('success', 'Router berhasil ditambahkan.');
    }

    public function test(Router $router, MikrotikRestClient $client): RedirectResponse
    {
        $this->ensureTenantOwnership($router);
        try {
            $resource = $client->systemResource($router);
            $router->update([
                'status' => 'online',
                'routeros_version' => $resource['version'] ?? null,
                'board_name' => $resource['board-name'] ?? $resource['board_name'] ?? null,
                'last_seen_at' => now(),
                'last_error' => null,
            ]);

            return back()->with('success', 'Koneksi MikroTik berhasil.');
        } catch (Throwable $e) {
            $router->update([
                'status' => 'offline',
                'last_error' => mb_substr($e->getMessage(), 0, 1500),
            ]);

            return back()->with('error', 'Koneksi MikroTik gagal: '.$e->getMessage());
        }
    }

    public function destroy(Router $router): RedirectResponse
    {
        $this->ensureTenantOwnership($router);
        $router->delete();
        return back()->with('success', 'Router dihapus.');
    }
}
