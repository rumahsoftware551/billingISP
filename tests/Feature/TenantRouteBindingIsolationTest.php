<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TenantRouteBindingIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_implicit_binding_cannot_resolve_an_invoice_from_another_tenant(): void
    {
        Route::middleware(['web', 'auth', 'tenant'])
            ->get('/_test/tenant-binding/invoices/{invoice}', fn (Invoice $invoice) => response()->json(['id' => $invoice->id]));

        $tenantA = $this->createTenant('tenant-a');
        $tenantB = $this->createTenant('tenant-b');
        $invoiceA = $this->createInvoiceFor($tenantA, 'A');
        $invoiceB = $this->createInvoiceFor($tenantB, 'B');

        $user = User::create([
            'name' => 'Tenant A Operator',
            'email' => 'tenant-a-operator@example.test',
            'password' => 'test-password',
            'status' => 'active',
        ]);
        DB::table('tenant_memberships')->insert([
            'tenant_id' => $tenantA->id,
            'user_id' => $user->id,
            'is_default' => true,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/_test/tenant-binding/invoices/'.$invoiceA->id)
            ->assertOk()
            ->assertJsonPath('id', $invoiceA->id);

        $this->actingAs($user)
            ->get('/_test/tenant-binding/invoices/'.$invoiceB->id)
            ->assertNotFound();
    }

    private function createTenant(string $slug): Tenant
    {
        return Tenant::create([
            'name' => strtoupper($slug),
            'slug' => $slug,
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);
    }

    private function createInvoiceFor(Tenant $tenant, string $suffix): Invoice
    {
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));

        $customer = Customer::create([
            'customer_number' => 'CUS-'.$suffix,
            'name' => 'Customer '.$suffix,
            'status' => 'active',
        ]);

        return Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-'.$suffix,
            'billing_key' => 'test:invoice:'.$suffix,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'issued_at' => '2026-08-01',
            'due_at' => today()->addDay()->toDateString(),
            'subtotal' => 100000,
            'total' => 100000,
            'paid_amount' => 0,
            'balance_due' => 100000,
            'status' => 'unpaid',
        ]);
    }
}
