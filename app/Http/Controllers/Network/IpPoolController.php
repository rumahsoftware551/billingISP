<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\IpPool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IpPoolController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'start_ip' => ['required', 'ip'],
            'end_ip' => ['required', 'ip'],
            'gateway' => ['nullable', 'ip'],
        ]);
        IpPool::create($data + ['active' => true]);
        return back()->with('success', 'IP pool ditambahkan.');
    }

    public function destroy(IpPool $pool): RedirectResponse
    {
        $this->ensureTenantOwnership($pool);
        $pool->delete();
        return back()->with('success', 'IP pool dihapus.');
    }
}
