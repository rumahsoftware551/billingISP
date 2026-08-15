<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerContactController extends Controller
{
    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $this->ensureTenantOwnership($customer);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'type' => ['required', Rule::in(['phone', 'whatsapp', 'email', 'other'])],
            'value' => ['required', 'string', 'max:190'],
            'is_primary' => ['boolean'],
        ]);

        DB::transaction(function () use ($customer, $data) {
            if ($data['is_primary'] ?? false) {
                $customer->contacts()->update(['is_primary' => false]);
            }
            $customer->contacts()->create($data);
        });

        return back()->with('success', 'Kontak ditambahkan.');
    }

    public function destroy(Customer $customer, CustomerContact $contact): RedirectResponse
    {
        $this->ensureTenantOwnership($customer);
        $this->ensureTenantOwnership($contact);
        abort_unless($contact->customer_id === $customer->id, 404);
        $contact->delete();
        return back()->with('success', 'Kontak dihapus.');
    }
}
