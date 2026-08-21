<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InternetPlan;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantIntegrityConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_cannot_reference_a_plan_from_another_tenant(): void
    {
        [$currentTenant, $otherTenant] = $this->tenants();
        $customer = $this->customer($currentTenant, 'CUST-A');
        $foreignPlan = InternetPlan::query()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Plan',
            'code' => 'FOREIGN-10M',
            'price' => 150000,
            'download_kbps' => 10000,
            'upload_kbps' => 5000,
            'active' => true,
        ]);

        $this->expectException(QueryException::class);

        DB::table('customer_services')->insert([
            'tenant_id' => $currentTenant->id,
            'customer_id' => $customer->id,
            'internet_plan_id' => $foreignPlan->id,
            'service_number' => 'SRV-CROSS-TENANT',
            'service_type' => 'pppoe',
            'pppoe_username' => 'cross-tenant-test',
            'pppoe_password_encrypted' => 'not-a-real-secret',
            'status' => 'draft',
            'billing_day' => 1,
            'due_day' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_allocation_cannot_mix_payment_and_invoice_tenants(): void
    {
        [$currentTenant, $otherTenant] = $this->tenants();
        $currentCustomer = $this->customer($currentTenant, 'CUST-A');
        $otherCustomer = $this->customer($otherTenant, 'CUST-B');
        $invoice = Invoice::query()->create([
            'tenant_id' => $currentTenant->id,
            'customer_id' => $currentCustomer->id,
            'invoice_number' => 'INV-TENANT-A',
            'billing_key' => 'tenant-a:integrity-test',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'issued_at' => '2026-08-01',
            'due_at' => '2026-08-10',
            'subtotal' => 100000,
            'total' => 100000,
            'balance_due' => 100000,
            'status' => 'unpaid',
        ]);
        $foreignPayment = Payment::query()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'payment_number' => 'PAY-TENANT-B',
            'amount' => 100000,
            'method' => 'cash',
            'paid_at' => now(),
            'status' => 'posted',
        ]);

        $this->expectException(QueryException::class);

        DB::table('payment_allocations')->insert([
            'tenant_id' => $currentTenant->id,
            'payment_id' => $foreignPayment->id,
            'invoice_id' => $invoice->id,
            'amount' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array{Tenant,Tenant} */
    private function tenants(): array
    {
        $currentTenant = $this->createTenant('tenant-a');
        $otherTenant = Tenant::query()->create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);

        return [$currentTenant, $otherTenant];
    }

    private function customer(Tenant $tenant, string $number): Customer
    {
        return Customer::query()->create([
            'tenant_id' => $tenant->id,
            'customer_number' => $number,
            'name' => 'Customer '.$number,
            'status' => 'active',
        ]);
    }
}
