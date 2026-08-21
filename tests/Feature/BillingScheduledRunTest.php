<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPlan;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\BillingEngine;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingScheduledRunTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance(CurrentTenant::class);
        parent::tearDown();
    }

    public function test_scheduled_run_only_generates_invoices_for_services_whose_billing_day_is_due(): void
    {
        $tenant = $this->createTenant('scheduled');
        $this->bindTenant($tenant);

        $customer = $this->createCustomer('SCHED');
        $plan = $this->createPlan();

        $dueService = $this->createService($customer, $plan, billingDay: 5, suffix: 'DUE');
        $futureService = $this->createService($customer, $plan, billingDay: 20, suffix: 'FUTURE');

        $run = app(BillingEngine::class)->runDueForTenant(
            $tenant,
            CarbonImmutable::parse('2026-08-10')
        );

        $this->assertSame(1, $run->eligible_count);
        $this->assertSame(1, $run->created_count);
        $this->assertSame(0, $run->skipped_count);
        $this->assertSame(0, $run->error_count);

        $this->assertDatabaseHas('invoices', [
            'customer_service_id' => $dueService->id,
            'billing_key' => 'service:'.$dueService->id.':2026-08',
        ]);

        $this->assertDatabaseMissing('invoices', [
            'customer_service_id' => $futureService->id,
            'billing_key' => 'service:'.$futureService->id.':2026-08',
        ]);
    }

    public function test_scheduled_run_is_idempotent_and_catches_up_after_billing_day(): void
    {
        $tenant = $this->createTenant('catchup');
        $this->bindTenant($tenant);

        $customer = $this->createCustomer('CATCH');
        $plan = $this->createPlan();
        $service = $this->createService($customer, $plan, billingDay: 5, suffix: 'CATCH');

        $asOf = CarbonImmutable::parse('2026-08-12');

        $first = app(BillingEngine::class)->runDueForTenant($tenant, $asOf);
        $second = app(BillingEngine::class)->runDueForTenant($tenant, $asOf);

        $this->assertSame(1, $first->created_count);
        $this->assertSame(0, $second->created_count);
        $this->assertSame(1, $second->skipped_count);

        $this->assertSame(
            1,
            Invoice::query()
                ->where('customer_service_id', $service->id)
                ->where('billing_key', 'service:'.$service->id.':2026-08')
                ->count()
        );

        $invoice = Invoice::query()->where('customer_service_id', $service->id)->firstOrFail();
        $this->assertSame('2026-08-05', $invoice->issued_at->toDateString());
    }

    private function createTenant(string $suffix): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Billing Tenant '.Str::upper($suffix),
            'slug' => 'billing-'.$suffix.'-'.Str::lower(Str::random(6)),
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);
    }

    private function createCustomer(string $prefix): Customer
    {
        return Customer::query()->create([
            'customer_number' => $prefix.'-'.Str::upper(Str::random(8)),
            'name' => 'Customer '.$prefix,
            'customer_type' => 'residential',
            'status' => 'active',
        ]);
    }

    private function createPlan(): InternetPlan
    {
        return InternetPlan::query()->create([
            'name' => 'Internet 50 Mbps',
            'code' => 'P50-'.Str::upper(Str::random(6)),
            'price' => 250000,
            'download_kbps' => 50000,
            'upload_kbps' => 25000,
            'active' => true,
        ]);
    }

    private function createService(
        Customer $customer,
        InternetPlan $plan,
        int $billingDay,
        string $suffix
    ): CustomerService {
        return CustomerService::query()->create([
            'customer_id' => $customer->id,
            'internet_plan_id' => $plan->id,
            'service_number' => 'SRV-'.$suffix.'-'.Str::upper(Str::random(5)),
            'service_type' => 'pppoe',
            'pppoe_username' => 'test-'.Str::lower(Str::random(10)),
            'pppoe_password' => 'Test@12345',
            'status' => 'active',
            'billing_day' => $billingDay,
            'due_day' => min(28, $billingDay + 7),
            'installed_at' => CarbonImmutable::parse('2026-08-01'),
        ]);
    }

    private function bindTenant(Tenant $tenant): void
    {
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));
    }
}
