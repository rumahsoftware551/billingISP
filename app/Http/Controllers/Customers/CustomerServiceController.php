<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\ServiceStatusHistory;
use App\Models\ServiceSuspension;
use App\Services\RadiusCoaService;
use App\Services\RadiusProjectionService;
use App\Services\PartnerCommissionService;
use App\Services\TenantSequenceService;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerServiceController extends Controller
{
    public function store(Request $request, Customer $customer, TenantSequenceService $sequences, RadiusProjectionService $radius, PartnerCommissionService $commissions): RedirectResponse
    {
        $this->ensureTenantOwnership($customer);
        $tenantId = app(CurrentTenant::class)->id();
        $data = $this->validated($request, $tenantId);
        unset($data['status_reason']);

        $service = DB::transaction(function () use ($customer, $data, $tenantId, $sequences) {
            $service = $customer->services()->create([
                ...$data,
                'service_number' => $sequences->next($tenantId, 'service', 'SRV-'),
                'service_type' => 'pppoe',
                'installed_at' => $data['status'] === 'active' ? now() : null,
            ]);

            ServiceStatusHistory::create([
                'customer_service_id' => $service->id,
                'from_status' => null,
                'to_status' => $service->status,
                'reason' => 'Layanan dibuat',
                'actor_user_id' => auth()->id(),
            ]);

            return $service;
        });

        $radius->syncService($service);
        if ($service->status === 'active') { try { $commissions->accrueActivationForService($service->fresh()); } catch (\Throwable $e) { report($e); } }
        return back()->with('success', 'Layanan PPPoE berhasil dibuat dan projection RADIUS diperbarui.');
    }

    public function update(Request $request, Customer $customer, CustomerService $service, RadiusProjectionService $radius, RadiusCoaService $coa, PartnerCommissionService $commissions): RedirectResponse
    {
        $this->ensureTenantOwnership($customer);
        $this->ensureTenantOwnership($service);
        $this->assertBelongsToCustomer($customer, $service);
        $tenantId = app(CurrentTenant::class)->id();
        $data = $this->validated($request, $tenantId, $service->id, true);
        $statusReason = $data['status_reason'] ?? null;
        unset($data['status_reason']);
        if (empty($data['pppoe_password'])) {
            unset($data['pppoe_password']);
        }
        $oldStatus = $service->status;
        $oldUsername = $service->pppoe_username;
        $oldPlanId = $service->internet_plan_id;

        DB::transaction(function () use ($service, $data, $oldStatus, $statusReason) {
            if (($data['status'] ?? $service->status) === 'active' && ! $service->installed_at) {
                $data['installed_at'] = now();
            }

            $service->update($data);

            if ($oldStatus !== $service->status) {
                ServiceStatusHistory::create([
                    'customer_service_id' => $service->id,
                    'from_status' => $oldStatus,
                    'to_status' => $service->status,
                    'reason' => $statusReason ?: 'Status layanan diperbarui',
                    'actor_user_id' => auth()->id(),
                ]);

                if ($service->status === 'suspended') {
                    ServiceSuspension::create([
                        'customer_service_id' => $service->id,
                        'source' => 'manual',
                        'status' => 'active',
                        'reason' => $statusReason ?: 'Suspensi manual oleh operator.',
                        'suspended_at' => now(),
                        'metadata' => ['actor_user_id' => auth()->id()],
                    ]);
                } elseif ($oldStatus === 'suspended' && $service->status === 'active') {
                    ServiceSuspension::query()
                        ->where('customer_service_id', $service->id)
                        ->where('source', 'manual')
                        ->where('status', 'active')
                        ->update(['status' => 'resolved', 'resolved_at' => now(), 'updated_at' => now()]);
                }
            }
        });

        if ($oldUsername !== $service->pppoe_username) {
            $radius->removeServiceByUsername($oldUsername);
        }

        $fresh = $service->fresh(['plan', 'nas']);
        $radius->syncService($fresh);
        $notes = ['Layanan diperbarui dan RADIUS disinkronkan.'];

        if ($oldUsername !== $fresh->pppoe_username) {
            $result = $coa->disconnectAllForUsername($fresh, $oldUsername, auth()->id());
            if ($result['attempted'] > 0) {
                $notes[] = sprintf('Session username lama: %d disconnect berhasil, %d gagal.', $result['succeeded'], $result['failed']);
            }
        }

        if ($oldStatus === 'active' && $fresh->status !== 'active') {
            $result = $coa->disconnectAllForService($fresh, auth()->id());
            if ($result['attempted'] > 0) {
                $notes[] = sprintf('Lifecycle disconnect: %d berhasil, %d gagal.', $result['succeeded'], $result['failed']);
            }
        } elseif ($fresh->status === 'active' && $oldPlanId !== $fresh->internet_plan_id) {
            $result = $coa->applyPlanToAllSessions($fresh, auth()->id());
            if ($result['attempted'] > 0) {
                $notes[] = sprintf('CoA paket baru: %d berhasil, %d gagal.', $result['succeeded'], $result['failed']);
            }
        }

        if ($oldStatus !== 'active' && $fresh->status === 'active') {
            try { $commissions->accrueActivationForService($fresh); } catch (\Throwable $e) { report($e); }
        }

        return back()->with('success', implode(' ', $notes));
    }

    public function sync(Customer $customer, CustomerService $service, RadiusProjectionService $radius): RedirectResponse
    {
        $this->ensureTenantOwnership($customer);
        $this->ensureTenantOwnership($service);
        $this->assertBelongsToCustomer($customer, $service);
        $radius->syncService($service);
        return back()->with('success', 'Projection RADIUS layanan disinkronkan.');
    }

    public function destroy(Customer $customer, CustomerService $service, RadiusProjectionService $radius, RadiusCoaService $coa): RedirectResponse
    {
        $this->ensureTenantOwnership($customer);
        $this->ensureTenantOwnership($service);
        $this->assertBelongsToCustomer($customer, $service);

        $disconnect = $coa->disconnectAllForService($service, auth()->id());

        DB::transaction(function () use ($service, $radius) {
            $radius->removeService($service);
            ServiceStatusHistory::create([
                'customer_service_id' => $service->id,
                'from_status' => $service->status,
                'to_status' => 'terminated',
                'reason' => 'Layanan dihapus/diterminasi',
                'actor_user_id' => auth()->id(),
            ]);
            $service->forceFill(['status' => 'terminated'])->saveQuietly();
            $service->delete();
        });

        return back()->with('success', sprintf('Layanan diterminasi dan akses RADIUS dihapus. Session disconnect: %d berhasil, %d gagal.', $disconnect['succeeded'], $disconnect['failed']));
    }

    private function validated(Request $request, string $tenantId, ?int $ignoreServiceId = null, bool $passwordOptional = false): array
    {
        $existsForTenant = fn (string $table) => Rule::exists($table, 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId));
        $usernameRule = Rule::unique('customer_services', 'pppoe_username');
        if ($ignoreServiceId) {
            $usernameRule = $usernameRule->ignore($ignoreServiceId);
        }

        $currentUsername = $ignoreServiceId
            ? DB::table('customer_services')->where('id', $ignoreServiceId)->value('pppoe_username')
            : null;

        return $request->validate([
            'internet_plan_id' => ['required', $existsForTenant('internet_plans')],
            'router_id' => ['nullable', $existsForTenant('routers')],
            'network_nas_id' => ['nullable', $existsForTenant('network_nas')],
            'ip_pool_id' => ['nullable', $existsForTenant('ip_pools')],
            'pppoe_username' => [
                'required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._@:\-]+$/', $usernameRule,
                function (string $attribute, mixed $value, \Closure $fail) use ($currentUsername) {
                    if ($value !== $currentUsername && DB::table('radcheck')->where('username', $value)->exists()) {
                        $fail('PPPoE username sudah digunakan pada projection RADIUS.');
                    }
                },
            ],
            'pppoe_password' => [$passwordOptional ? 'nullable' : 'required', 'string', 'min:6', 'max:190'],
            'status' => ['required', Rule::in(['draft', 'pending_installation', 'active', 'suspended', 'terminated'])],
            'billing_day' => ['required', 'integer', 'between:1,28'],
            'due_day' => ['required', 'integer', 'between:1,28'],
            'static_ip' => ['nullable', 'ip'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status_reason' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function assertBelongsToCustomer(Customer $customer, CustomerService $service): void
    {
        abort_unless($service->customer_id === $customer->id, 404);
    }
}
