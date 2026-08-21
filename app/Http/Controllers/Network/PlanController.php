<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\InternetPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40'],
            'price' => ['required', 'integer', 'min:0'],
            'download_kbps' => ['required', 'integer', 'min:1'],
            'upload_kbps' => ['required', 'integer', 'min:1'],
            'acct_interim_interval' => ['required', 'integer', 'between:60,3600'],
        ]);

        $data['code'] = strtoupper($data['code']);
        $data['active'] = true;
        $data['radius_attributes'] = [
            'Mikrotik-Rate-Limit' => sprintf('%dk/%dk', $data['upload_kbps'], $data['download_kbps']),
        ];

        InternetPlan::create($data);
        return back()->with('success', 'Paket internet ditambahkan.');
    }

    public function destroy(InternetPlan $plan): RedirectResponse
    {
        $this->ensureTenantOwnership($plan);
        if ($plan->services()->exists()) {
            return back()->with('error', 'Paket masih dipakai layanan pelanggan dan tidak dapat dihapus.');
        }
        $plan->delete();
        return back()->with('success', 'Paket dihapus.');
    }
}
