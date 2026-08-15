<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\NetworkNas;
use App\Services\RadiusProjectionService;
use Illuminate\Http\RedirectResponse;
use App\Support\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NasController extends Controller
{
    public function store(Request $request, RadiusProjectionService $projection): RedirectResponse
    {
        $data = $request->validate([
            'router_id' => ['nullable', 'integer', Rule::exists('routers', 'id')->where('tenant_id', app(CurrentTenant::class)->id())],
            'nasname' => ['required', 'string', 'max:255', Rule::unique('network_nas', 'nasname')],
            'shortname' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', 'max:60'],
            'secret' => ['required', 'string', 'min:8', 'max:255'],
            'coa_port' => ['required', 'integer', 'between:1,65535'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $nas = NetworkNas::create($data + ['active' => true]);
        $projection->syncNas($nas);

        return back()->with('success', 'NAS tersimpan dan diproyeksikan ke tabel RADIUS. Restart FreeRADIUS diperlukan agar SQL client baru terbaca.');
    }

    public function sync(NetworkNas $nas, RadiusProjectionService $projection): RedirectResponse
    {
        $this->ensureTenantOwnership($nas);
        $projection->syncNas($nas);
        return back()->with('success', 'Projection NAS diperbarui. Restart FreeRADIUS untuk memuat client SQL.');
    }

    public function destroy(NetworkNas $nas, RadiusProjectionService $projection): RedirectResponse
    {
        $this->ensureTenantOwnership($nas);
        $projection->removeNas($nas);
        $nas->delete();
        return back()->with('success', 'NAS dihapus.');
    }
}
