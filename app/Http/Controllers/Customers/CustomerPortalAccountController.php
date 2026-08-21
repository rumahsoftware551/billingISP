<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerPortalAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerPortalAccountController extends Controller
{
    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $this->ensureTenantOwnership($customer);
        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:190'],
            'password' => ['nullable', 'string', 'min:10', 'max:200'],
        ]);
        $email = $data['email'] ?: $customer->email;
        if (! $email) return back()->with('error', 'Email portal wajib diisi atau simpan email pada profil pelanggan terlebih dahulu.');

        $duplicate = CustomerPortalAccount::query()->where('tenant_id', $customer->tenant_id)->whereRaw('LOWER(email) = ?', [Str::lower($email)])->where('customer_id', '!=', $customer->id)->exists();
        if ($duplicate) return back()->with('error', 'Email portal sudah digunakan pelanggan lain pada tenant ini.');

        $password = $data['password'] ?: $this->temporaryPassword();
        CustomerPortalAccount::query()->updateOrCreate(
            ['customer_id' => $customer->id],
            [
                'tenant_id' => $customer->tenant_id,
                'email' => Str::lower($email),
                'password' => Hash::make($password),
                'status' => 'active',
                'must_change_password' => true,
                'portal_enabled_at' => now(),
            ]
        );

        return back()->with('success', 'Portal pelanggan berhasil diaktifkan.')
            ->with('generated_portal_password', $password);
    }

    public function resetPassword(Request $request, Customer $customer): RedirectResponse
    {
        $this->ensureTenantOwnership($customer);
        $account = CustomerPortalAccount::query()->where('customer_id', $customer->id)->where('tenant_id', $customer->tenant_id)->firstOrFail();
        $data = $request->validate(['password' => ['nullable', 'string', 'min:10', 'max:200']]);
        $password = $data['password'] ?: $this->temporaryPassword();
        $account->forceFill(['password' => Hash::make($password), 'must_change_password' => true, 'status' => 'active'])->save();
        return back()->with('success', 'Password portal direset.')->with('generated_portal_password', $password);
    }

    public function updateStatus(Request $request, Customer $customer): RedirectResponse
    {
        $this->ensureTenantOwnership($customer);
        $data = $request->validate(['status' => ['required', Rule::in(['active','disabled'])]]);
        CustomerPortalAccount::query()->where('customer_id', $customer->id)->where('tenant_id', $customer->tenant_id)->firstOrFail()->update(['status' => $data['status']]);
        return back()->with('success', 'Status portal diperbarui.');
    }

    private function temporaryPassword(): string
    {
        return 'Jk!'.Str::upper(Str::random(5)).'-'.Str::lower(Str::random(5)).random_int(10, 99);
    }
}
