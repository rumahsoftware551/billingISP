<?php

namespace Tests\Feature;

use App\Models\BillingProfile;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPlan;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\BillingAutomationService;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingGracePeriodTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance(CurrentTenant::class);
        parent::tearDown();
    }

    public function test_invoice_is_safe_through_grace_day_and_blocks_the_next_day(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Grace Tenant',
            'slug' => 'grace-'.Str::lower(Str::random(6)),
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));

        $customer = Customer::query()->create([
            'customer_number' => 'CUS-'.Str::upper(Str::random(8)),
            'name' => 'Grace Customer',
            'customer_type' => 'residential',
            'status' => 'active',
        ]);
        $plan = InternetPlan::query()->create([
            'name' => 'Grace Plan',
            'code' => 'GRACE-'.Str::upper(Str::random(5)),
            'price' => 200000,
            'download_kbps' => 20000,
            'upload_kbps' => 10000,
            'active' => true,
        ]);
        $service = CustomerService::query()->create([
            'customer_id' => $customer->id,
            'internet_plan_id' => $plan->id,
            'service_number' => 'SRV-'.Str::upper(Str::random(8)),
            'service_type' => 'pppoe',
            'pppoe_username' => 'grace-'.Str::lower(Str::random(8)),
            'pppoe_password' => 'Test@12345',
            'status' => 'active',
            'billing_day' => 1,
            'due_day' => 10,
            'installed_at' => now()->subMonth(),
        ]);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'customer_service_id' => $service->id,
            'invoice_number' => 'INV-GRACE-'.Str::upper(Str::random(6)),
            'billing_key' => 'grace:'.Str::uuid(),
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'issued_at' => '2026-08-01',
            'due_at' => '2026-08-10',
            'subtotal' => 200000,
            'total' => 200000,
            'paid_amount' => 0,
            'balance_due' => 200000,
            'status' => 'overdue',
        ]);
        $policy = BillingProfile::query()->create([
            'name' => 'Grace Test Policy',
            'invoice_day' => 1,
            'due_day' => 10,
            'grace_days' => 3,
            'auto_suspend' => true,
            'auto_reactivate' => true,
            'disconnect_on_suspend' => true,
            'active' => true,
        ]);

        $automation = app(BillingAutomationService::class);

        $this->assertNull($automation->blockingInvoice($service, $policy, CarbonImmutable::parse('2026-08-13')));
        $this->assertSame($invoice->id, $automation->blockingInvoice($service, $policy, CarbonImmutable::parse('2026-08-14'))?->id);
    }
}
