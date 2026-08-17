<?php

namespace App\Services;

use App\Models\BillingRun;
use App\Models\CustomerService;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class BillingEngine
{
    public function __construct(private TenantSequenceService $sequences, private PaymentNotificationService $notifications) {}

    public function generateForService(CustomerService $service, CarbonImmutable $periodStart): Invoice
    {
        $service->loadMissing(['plan', 'customer']);
        $periodStart = $periodStart->startOfMonth();
        $periodEnd = $periodStart->endOfMonth();
        $tenantId = (string) $service->tenant_id;
        $billingKey = 'service:'.$service->id.':'.$periodStart->format('Y-m');

        $invoice = DB::transaction(function () use ($service, $periodStart, $periodEnd, $tenantId, $billingKey) {
            $existing = Invoice::query()->where('billing_key', $billingKey)->lockForUpdate()->first();
            if ($existing) {
                return $existing->load(['items', 'customer', 'service.plan']);
            }

            $issueDay = max(1, min(28, (int) $service->billing_day));
            $dueDay = max(1, min(28, (int) $service->due_day));
            $issuedAt = $periodStart->setDay($issueDay);
            $dueAt = $dueDay >= $issueDay
                ? $periodStart->setDay($dueDay)
                : $periodStart->addMonthNoOverflow()->setDay($dueDay);

            $price = max(0, (int) $service->plan->price);
            $invoiceNumber = $this->sequences->next(
                $tenantId,
                'invoice:'.$periodStart->format('Ym'),
                'INV-'.$periodStart->format('Ym').'-',
                5
            );

            $invoice = Invoice::create([
                'customer_id' => $service->customer_id,
                'customer_service_id' => $service->id,
                'invoice_number' => $invoiceNumber,
                'billing_key' => $billingKey,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'issued_at' => $issuedAt->toDateString(),
                'due_at' => $dueAt->toDateString(),
                'subtotal' => $price,
                'discount' => 0,
                'tax' => 0,
                'total' => $price,
                'paid_amount' => 0,
                'balance_due' => $price,
                'status' => 'unpaid',
                'notes' => 'Generated otomatis oleh Billing Engine Jaringanku.',
            ]);

            $invoice->items()->create([
                'description' => sprintf('Layanan internet %s - %s', $service->plan->name, $periodStart->translatedFormat('F Y')),
                'quantity' => 1,
                'unit_price' => $price,
                'amount' => $price,
                'meta' => [
                    'service_number' => $service->service_number,
                    'pppoe_username' => $service->pppoe_username,
                    'plan_code' => $service->plan->code,
                    'plan_name' => $service->plan->name,
                    'download_kbps' => $service->plan->download_kbps,
                    'upload_kbps' => $service->plan->upload_kbps,
                ],
            ]);

            return $invoice->load(['items', 'customer', 'service.plan']);
        }, 3);

        if ($invoice->wasRecentlyCreated) {
            $this->notifications->invoiceCreated($invoice);
        }
        return $invoice;
    }

    public function runDueForTenant(Tenant $tenant, CarbonImmutable $asOf, ?int $actorUserId = null): BillingRun
    {
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));

        $asOf = $asOf->startOfDay();
        $periodStart = $asOf->startOfMonth();
        $periodEnd = $periodStart->endOfMonth();
        $effectiveBillingDay = min(28, $asOf->day);
        $runKey = 'scheduled:'.$asOf->format('Y-m-d');

        $run = BillingRun::query()->firstOrCreate(
            ['run_key' => $runKey],
            ['period_start' => $periodStart, 'period_end' => $periodEnd]
        );

        $run->forceFill([
            'status' => 'running',
            'eligible_count' => 0,
            'created_count' => 0,
            'skipped_count' => 0,
            'error_count' => 0,
            'errors' => null,
            'initiated_by' => $actorUserId,
            'started_at' => now(),
            'finished_at' => null,
        ])->save();

        $errors = [];
        $eligible = 0;
        $created = 0;
        $skipped = 0;

        CustomerService::query()
            ->with(['plan', 'customer'])
            ->where('status', 'active')
            ->where('billing_day', '<=', $effectiveBillingDay)
            ->where(function ($query) use ($asOf) {
                $query->whereNull('installed_at')->orWhere('installed_at', '<=', $asOf->endOfDay());
            })
            ->orderBy('id')
            ->chunkById(100, function ($services) use ($periodStart, &$eligible, &$created, &$skipped, &$errors) {
                foreach ($services as $service) {
                    $eligible++;
                    $billingKey = 'service:'.$service->id.':'.$periodStart->format('Y-m');
                    $already = Invoice::query()->where('billing_key', $billingKey)->exists();

                    try {
                        $this->generateForService($service, $periodStart);
                        $already ? $skipped++ : $created++;
                    } catch (Throwable $e) {
                        $errors[] = [
                            'service_id' => $service->id,
                            'service_number' => $service->service_number,
                            'message' => $e->getMessage(),
                        ];
                    }
                }
            });

        $run->forceFill([
            'status' => empty($errors) ? 'completed' : 'completed_with_errors',
            'eligible_count' => $eligible,
            'created_count' => $created,
            'skipped_count' => $skipped,
            'error_count' => count($errors),
            'errors' => empty($errors) ? null : $errors,
            'finished_at' => now(),
        ])->save();

        $this->refreshStatuses($tenant);

        return $run->fresh();
    }

    public function runForTenant(Tenant $tenant, CarbonImmutable $periodStart, ?int $actorUserId = null): BillingRun
    {
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));
        $periodStart = $periodStart->startOfMonth();
        $periodEnd = $periodStart->endOfMonth();
        $runKey = 'monthly:'.$periodStart->format('Y-m');

        $run = BillingRun::query()->firstOrCreate(
            ['run_key' => $runKey],
            ['period_start' => $periodStart, 'period_end' => $periodEnd]
        );

        $run->forceFill([
            'status' => 'running',
            'eligible_count' => 0,
            'created_count' => 0,
            'skipped_count' => 0,
            'error_count' => 0,
            'errors' => null,
            'initiated_by' => $actorUserId,
            'started_at' => now(),
            'finished_at' => null,
        ])->save();

        $errors = [];
        $eligible = 0;
        $created = 0;
        $skipped = 0;

        CustomerService::query()
            ->with(['plan', 'customer'])
            ->where('status', 'active')
            ->where(function ($query) use ($periodEnd) {
                $query->whereNull('installed_at')->orWhere('installed_at', '<=', $periodEnd->endOfDay());
            })
            ->orderBy('id')
            ->chunkById(100, function ($services) use ($periodStart, &$eligible, &$created, &$skipped, &$errors) {
                foreach ($services as $service) {
                    $eligible++;
                    $billingKey = 'service:'.$service->id.':'.$periodStart->format('Y-m');
                    $already = Invoice::query()->where('billing_key', $billingKey)->exists();
                    try {
                        $this->generateForService($service, $periodStart);
                        $already ? $skipped++ : $created++;
                    } catch (Throwable $e) {
                        $errors[] = [
                            'service_id' => $service->id,
                            'service_number' => $service->service_number,
                            'message' => $e->getMessage(),
                        ];
                    }
                }
            });

        $run->forceFill([
            'status' => empty($errors) ? 'completed' : 'completed_with_errors',
            'eligible_count' => $eligible,
            'created_count' => $created,
            'skipped_count' => $skipped,
            'error_count' => count($errors),
            'errors' => empty($errors) ? null : $errors,
            'finished_at' => now(),
        ])->save();

        $this->refreshStatuses($tenant);

        return $run->fresh();
    }

    public function refreshStatuses(Tenant $tenant): int
    {
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));
        $changed = 0;

        Invoice::query()
            ->whereIn('status', ['unpaid', 'partial', 'overdue'])
            ->orderBy('id')
            ->chunkById(200, function ($invoices) use (&$changed) {
                foreach ($invoices as $invoice) {
                    $status = $this->statusFor($invoice);
                    if ($invoice->status !== $status) {
                        $invoice->update(['status' => $status]);
                        $changed++;
                    }
                }
            });

        return $changed;
    }

    public function statusFor(Invoice $invoice): string
    {
        if ((int) $invoice->balance_due <= 0) {
            return 'paid';
        }
        if ($invoice->due_at && $invoice->due_at->isBefore(today())) {
            return 'overdue';
        }
        if ((int) $invoice->paid_amount > 0) {
            return 'partial';
        }
        return 'unpaid';
    }
}
