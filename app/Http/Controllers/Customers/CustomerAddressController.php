<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerAddressController extends Controller
{
    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $this->ensureTenantOwnership($customer);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'address_line' => ['required', 'string', 'max:1000'],
            'village' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_primary' => ['boolean'],
        ]);

        DB::transaction(function () use ($customer, $data) {
            if ($data['is_primary'] ?? false) {
                $customer->addresses()->update(['is_primary' => false]);
            }
            $customer->addresses()->create($data);
        });

        return back()->with('success', 'Alamat ditambahkan.');
    }

    public function destroy(Customer $customer, CustomerAddress $address): RedirectResponse
    {
        $this->ensureTenantOwnership($customer);
        $this->ensureTenantOwnership($address);
        abort_unless($address->customer_id === $customer->id, 404);
        $address->delete();
        return back()->with('success', 'Alamat dihapus.');
    }
}
