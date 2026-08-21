<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPlan;
use App\Models\IpPool;
use App\Models\NetworkNas;
use App\Models\Router;
use App\Services\TenantSequenceService;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('q'));
        $status = trim((string) $request->string('status'));
        if (! in_array($status, ['', 'active', 'inactive'], true)) { $status = ''; }

        $customers = Customer::query()
            ->withCount('services')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $like = '%'.$search.'%';
                    $q->where('customer_number', 'ilike', $like)
                        ->orWhere('name', 'ilike', $like)
                        ->orWhere('phone', 'ilike', $like)
                        ->orWhere('email', 'ilike', $like);
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => ['q' => $search, 'status' => $status],
            'stats' => [
                'customers' => Customer::count(),
                'active_customers' => Customer::where('status', 'active')->count(),
                'services' => CustomerService::count(),
                'active_services' => CustomerService::where('status', 'active')->count(),
            ],
        ]);
    }

    public function store(Request $request, TenantSequenceService $sequences): RedirectResponse
    {
        $tenantId = app(CurrentTenant::class)->id();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'customer_type' => ['required', Rule::in(['residential', 'business'])],
            'identity_number' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'secondary_phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'address_line' => ['nullable', 'string', 'max:1000'],
            'village' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
        ]);

        $customer = DB::transaction(function () use ($data, $tenantId, $sequences) {
            $customer = Customer::create([
                'customer_number' => $sequences->next($tenantId, 'customer', 'JRG-'),
                'name' => $data['name'],
                'customer_type' => $data['customer_type'],
                'identity_number' => $data['identity_number'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'secondary_phone' => $data['secondary_phone'] ?? null,
                'status' => 'active',
                'notes' => $data['notes'] ?? null,
            ]);

            if (! empty($data['address_line'])) {
                $customer->addresses()->create([
                    'label' => 'Instalasi',
                    'address_line' => $data['address_line'],
                    'village' => $data['village'] ?? null,
                    'district' => $data['district'] ?? null,
                    'city' => $data['city'] ?? null,
                    'province' => $data['province'] ?? null,
                    'postal_code' => $data['postal_code'] ?? null,
                    'is_primary' => true,
                ]);
            }

            return $customer;
        });

        return redirect()->route('customers.show', $customer)->with('success', 'Pelanggan berhasil dibuat.');
    }

    public function show(Customer $customer): Response
    {
        $this->ensureTenantOwnership($customer);
        $customer->load([
            'addresses' => fn ($q) => $q->orderByDesc('is_primary')->latest(),
            'contacts' => fn ($q) => $q->orderByDesc('is_primary')->latest(),
            'services' => fn ($q) => $q->with([
                'plan:id,name,code,price,download_kbps,upload_kbps',
                'router:id,name',
                'nas:id,shortname,nasname',
                'ipPool:id,name',
                'statusHistories' => fn ($history) => $history->with('actor:id,name')->latest()->limit(10),
                'accountingSessions' => fn ($session) => $session->online()->orderByDesc('acctstarttime')->limit(5),
            ])->latest(),
            'invoices' => fn ($q) => $q->with('service:id,service_number,pppoe_username')->latest('issued_at')->limit(12),
            'portalAccount:id,tenant_id,customer_id,email,status,must_change_password,last_login_at,portal_enabled_at',
        ]);

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
            'plans' => InternetPlan::where('active', true)->orderBy('name')->get(['id', 'name', 'code', 'price', 'download_kbps', 'upload_kbps']),
            'routers' => Router::orderBy('name')->get(['id', 'name', 'host', 'status']),
            'nas' => NetworkNas::where('active', true)->orderBy('shortname')->get(['id', 'router_id', 'shortname', 'nasname']),
            'pools' => IpPool::where('active', true)->orderBy('name')->get(['id', 'name', 'start_ip', 'end_ip']),
            'serviceStatuses' => ['draft', 'pending_installation', 'active', 'suspended', 'terminated'],
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $this->ensureTenantOwnership($customer);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'customer_type' => ['required', Rule::in(['residential', 'business'])],
            'identity_number' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'secondary_phone' => ['nullable', 'string', 'max:40'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $customer->update($data);
        return back()->with('success', 'Data pelanggan diperbarui.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->ensureTenantOwnership($customer);
        if ($customer->services()->exists()) {
            return back()->with('error', 'Pelanggan masih memiliki layanan. Terminasi/hapus layanan terlebih dahulu.');
        }

        $customer->delete();
        return redirect()->route('customers.index')->with('success', 'Pelanggan dihapus.');
    }
}
