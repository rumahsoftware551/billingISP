<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\PortalContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class PortalProfileController extends Controller
{
    public function show(string $tenantSlug): Response
    {
        $account = PortalContext::account()->load('customer');
        return Inertia::render('Portal/Profile', [
            'portalTenantSlug' => $tenantSlug,
            'account' => [
                'email' => $account->email,
                'status' => $account->status,
                'must_change_password' => (bool) $account->must_change_password,
                'last_login_at' => $account->last_login_at?->toIso8601String(),
            ],
            'customer' => $account->customer->only('customer_number','name','email','phone'),
        ]);
    }

    public function password(Request $request, string $tenantSlug): RedirectResponse
    {
        $account = PortalContext::account();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);
        if (! $account->passwordMatches($data['current_password'])) {
            return back()->withErrors(['current_password' => 'Password saat ini tidak sesuai.']);
        }
        $account->forceFill(['password' => Hash::make($data['password']), 'must_change_password' => false])->save();
        return back()->with('success', 'Password portal berhasil diperbarui.');
    }
}
