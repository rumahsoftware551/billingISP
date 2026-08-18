<?php

namespace App\Services;

use App\Models\AutomationEvent;
use App\Models\AutomationRun;
use App\Models\BillingProfile;
use App\Models\CustomerService;
use App\Models\Invoice;
use App\Models\ServiceStatusHistory;
use App\Models\ServiceSuspension;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class BillingAutomationService
{
    public function __construct(
        private readonly BillingEngine $billing,
        private readonly RadiusProjectionService $radius,
        private readonly RadiusCoaService $coa,
        private readonly BillingCalendar $calendar,
    ) {}

    public function policy(): BillingProfile
    {
        return BillingProfile::query()->firstOrCreate(
            ['name' => 'Default Billing'],
            [
                'invoice_day' => 1,
                'due_day' => 10,
                'grace_days' => 3,
                'auto_suspend' => true,
                'auto_reactivate' => true,
                'disconnect_on_suspend' => true,
                'active' => true,
            ]
        );
    }

    public function blockingInvoice(
        CustomerService $service,
        ?BillingProfile $policy = null,
        ?CarbonImmutable $asOf = null,
    ): ?Invoice {
        $policy ??= $this->policy();
        $graceDays = max(0, (int) $policy->grace_days);
        $asOf ??= CarbonImmutable::today();
        $cutoff = $this->calendar->blockingCutoff($asOf, $graceDays)->toDateString();

        return Invoice::query()
            ->where('customer_service_id', $service->id)
            ->where('balance_due', '>', 0)
            ->whereNotIn('status', ['paid', 'void'])
            // Example: due 10, grace 3 -> still safe through 13, suspend on 14.
            ->whereDate('due_at', '<', $cutoff)
            ->orderBy('due_at')
            ->orderBy('id')
            ->first();
    }

    public function evaluateTenant(Tenant $tenant, string $source = 'scheduled', ?int $actorUserId = null): AutomationRun
    {
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));
        $this->billing->refreshStatuses($tenant);
        $policy = $this->policy();

        $run = AutomationRun::create([
            'run_key' => 'AUT-'.Str::upper((string) Str::ulid()),
            'source' => $source,
            'status' => 'running',
            'initiated_by' => $actorUserId,
            'started_at' => now(),
        ]);

        $counts = [
            'scanned_count' => 0,
            'suspended_count' => 0,
            'reactivated_count' => 0,
            'enforced_count' => 0,
            'skipped_count' => 0,
            'error_count' => 0,
        ];
        $errors = [];

        CustomerService::query()
            ->whereIn('status', ['active', 'suspended'])
            ->orderBy('id')
            ->chunkById(100, function ($services) use ($policy, $run, $source, $actorUserId, &$counts, &$errors) {
                foreach ($services as $service) {
                    $counts['scanned_count']++;
                    try {
                        $result = $this->evaluateServiceInternal($service, $policy, $run, $source, $actorUserId, null);
                        $bucket = match ($result['action']) {
                            'suspended' => 'suspended_count',
                            'reactivated', 'resolved' => 'reactivated_count',
                            'enforced' => 'enforced_count',
                            default => 'skipped_count',
                        };
                        $counts[$bucket]++;
                    } catch (Throwable $e) {
                        $counts['error_count']++;
                        $errors[] = [
                            'service_id' => $service->id,
                            'service_number' => $service->service_number,
                            'message' => $e->getMessage(),
                        ];
                        $this->safeEvent($run, $service, null, null, 'error', false, $e->getMessage(), ['source' => $source]);
                    }
                }
            });

        $run->forceFill([
            ...$counts,
            'status' => $counts['error_count'] > 0 ? 'completed_with_errors' : 'completed',
            'errors' => $errors ?: null,
            'finished_at' => now(),
        ])->save();

        return $run->fresh();
    }

    /**
     * Evaluate one service immediately. Used after payment and by the smoke test.
     *
     * @return array{action:string,run_id:int,invoice_id:?int,message:string}
     */
    public function evaluateService(
        CustomerService $service,
        string $source = 'manual',
        ?int $actorUserId = null,
        ?int $paymentId = null,
    ): array {
        $tenant = Tenant::query()->findOrFail($service->tenant_id);
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));
        $policy = $this->policy();

        $run = AutomationRun::create([
            'run_key' => 'AUT-'.Str::upper((string) Str::ulid()),
            'source' => $source,
            'status' => 'running',
            'initiated_by' => $actorUserId,
            'started_at' => now(),
        ]);

        try {
            $result = $this->evaluateServiceInternal($service, $policy, $run, $source, $actorUserId, $paymentId);
            $run->forceFill([
                'status' => 'completed',
                'scanned_count' => 1,
                'suspended_count' => $result['action'] === 'suspended' ? 1 : 0,
                'reactivated_count' => in_array($result['action'], ['reactivated', 'resolved'], true) ? 1 : 0,
                'enforced_count' => $result['action'] === 'enforced' ? 1 : 0,
                'skipped_count' => $result['action'] === 'skipped' ? 1 : 0,
                'finished_at' => now(),
            ])->save();

            return [...$result, 'run_id' => $run->id];
        } catch (Throwable $e) {
            $run->forceFill([
                'status' => 'failed',
                'scanned_count' => 1,
                'error_count' => 1,
                'errors' => [['service_id' => $service->id, 'message' => $e->getMessage()]],
                'finished_at' => now(),
            ])->save();
            $this->safeEvent($run, $service, null, $paymentId, 'error', false, $e->getMessage(), ['source' => $source]);
            throw $e;
        }
    }

    /** @return array{action:string,invoice_id:?int,message:string} */
    private function evaluateServiceInternal(
        CustomerService $service,
        BillingProfile $policy,
        AutomationRun $run,
        string $source,
        ?int $actorUserId,
        ?int $paymentId,
    ): array {
        $service = CustomerService::query()->whereKey($service->id)->firstOrFail();
        $blocking = $this->blockingInvoice($service, $policy);
        $activeSuspension = ServiceSuspension::query()
            ->where('customer_service_id', $service->id)
            ->where('source', 'billing_automation')
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if ($service->status === 'active' && $blocking && $policy->auto_suspend) {
            return $this->suspend($service, $blocking, $policy, $run, $source, $actorUserId);
        }

        if ($service->status === 'active' && $blocking && ! $policy->auto_suspend) {
            return ['action' => 'skipped', 'invoice_id' => $blocking->id, 'message' => 'Auto suspend dinonaktifkan.'];
        }

        if ($service->status === 'active' && ! $blocking && $activeSuspension) {
            $activeSuspension->forceFill([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by_payment_id' => $paymentId,
                'metadata' => [...($activeSuspension->metadata ?? []), 'resolved_source' => $source],
            ])->save();
            $this->event($run, $service, $activeSuspension->invoice, $paymentId, 'suspension_resolved', true, 'Catatan isolir billing diselesaikan; layanan sudah aktif.', ['source' => $source]);

            return ['action' => 'resolved', 'invoice_id' => $activeSuspension->invoice_id, 'message' => 'Suspension billing diselesaikan.'];
        }

        if ($service->status === 'suspended' && ! $activeSuspension) {
            // Never auto-reactivate a service suspended manually/operationally.
            return ['action' => 'skipped', 'invoice_id' => $blocking?->id, 'message' => 'Suspensi manual/operasional tidak disentuh automation billing.'];
        }

        if ($service->status === 'suspended' && $activeSuspension && $blocking) {
            return $this->enforceSuspension($service, $blocking, $policy, $run, $source, $actorUserId);
        }

        if ($service->status === 'suspended' && $activeSuspension && ! $blocking) {
            $manualHold = ServiceSuspension::query()
                ->where('customer_service_id', $service->id)
                ->where('source', 'manual')
                ->where('status', 'active')
                ->exists();
            if ($manualHold) {
                return ['action' => 'skipped', 'invoice_id' => $activeSuspension->invoice_id, 'message' => 'Tagihan clear tetapi layanan masih memiliki manual hold.'];
            }
            if (! $policy->auto_reactivate) {
                return ['action' => 'skipped', 'invoice_id' => $activeSuspension->invoice_id, 'message' => 'Tagihan clear tetapi auto reactivate dinonaktifkan.'];
            }

            return $this->reactivate($service, $activeSuspension, $run, $source, $actorUserId, $paymentId);
        }

        return ['action' => 'skipped', 'invoice_id' => null, 'message' => 'Tidak ada tindakan automation.'];
    }

    /** @return array{action:string,invoice_id:int,message:string} */
    private function suspend(
        CustomerService $service,
        Invoice $invoice,
        BillingProfile $policy,
        AutomationRun $run,
        string $source,
        ?int $actorUserId,
    ): array {
        $suspension = DB::transaction(function () use ($service, $invoice, $run, $source, $actorUserId) {
            $locked = CustomerService::query()->whereKey($service->id)->lockForUpdate()->firstOrFail();
            $existing = ServiceSuspension::query()
                ->where('customer_service_id', $locked->id)
                ->where('source', 'billing_automation')
                ->where('status', 'active')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            $suspension = $existing ?: ServiceSuspension::create([
                'customer_service_id' => $locked->id,
                'invoice_id' => $invoice->id,
                'source' => 'billing_automation',
                'status' => 'active',
                'reason' => 'Tagihan melewati jatuh tempo + grace period.',
                'suspended_at' => now(),
                'metadata' => ['automation_run_id' => $run->id, 'trigger_source' => $source],
            ]);

            if ($existing && $existing->invoice_id !== $invoice->id) {
                $existing->forceFill(['invoice_id' => $invoice->id])->save();
            }

            if ($locked->status !== 'suspended') {
                $from = $locked->status;
                $locked->forceFill(['status' => 'suspended'])->save();
                ServiceStatusHistory::create([
                    'customer_service_id' => $locked->id,
                    'from_status' => $from,
                    'to_status' => 'suspended',
                    'reason' => 'Auto isolir: invoice '.$invoice->invoice_number.' melewati grace period.',
                    'actor_user_id' => $actorUserId,
                    'metadata' => ['automation_run_id' => $run->id, 'invoice_id' => $invoice->id, 'source' => $source],
                ]);
            }

            // Suspended status is projected as Auth-Type := Reject and removes all active auth/reply rows atomically.
            $this->radius->syncService($locked->fresh(['plan', 'ipPool']));

            return $suspension;
        }, 3);

        $disconnect = ['attempted' => 0, 'succeeded' => 0, 'failed' => 0, 'errors' => []];
        if ($policy->disconnect_on_suspend) {
            $disconnect = $this->coa->disconnectAllForService($service->fresh(), $actorUserId);
        }

        $this->event(
            $run,
            $service,
            $invoice,
            null,
            'service_suspended',
            $disconnect['failed'] === 0,
            sprintf('Layanan diisolir. Disconnect session: %d berhasil, %d gagal.', $disconnect['succeeded'], $disconnect['failed']),
            ['source' => $source, 'suspension_id' => $suspension->id, 'disconnect' => $disconnect]
        );

        return ['action' => 'suspended', 'invoice_id' => $invoice->id, 'message' => 'Layanan diisolir otomatis.'];
    }

    /** @return array{action:string,invoice_id:int,message:string} */
    private function enforceSuspension(
        CustomerService $service,
        Invoice $invoice,
        BillingProfile $policy,
        AutomationRun $run,
        string $source,
        ?int $actorUserId,
    ): array {
        $hasRejectProjection = DB::table('radcheck')
            ->where('username', $service->pppoe_username)
            ->where('attribute', 'Auth-Type')
            ->whereRaw('LOWER(value) = ?', ['reject'])
            ->exists();
        $hasLeakedActiveProjection = DB::table('radcheck')
            ->where('username', $service->pppoe_username)
            ->where(function ($query) {
                $query->where('attribute', '<>', 'Auth-Type')
                    ->orWhereRaw('LOWER(value) <> ?', ['reject']);
            })
            ->exists()
            || DB::table('radreply')->where('username', $service->pppoe_username)->exists()
            || DB::table('radusergroup')->where('username', $service->pppoe_username)->exists();
        $hasOnlineSession = DB::table('radacct')->where('username', $service->pppoe_username)->whereNull('acctstoptime')->exists();

        if ($hasRejectProjection && ! $hasLeakedActiveProjection && ! $hasOnlineSession) {
            return ['action' => 'skipped', 'invoice_id' => $invoice->id, 'message' => 'Isolir billing sudah enforced.'];
        }

        if (! $hasRejectProjection || $hasLeakedActiveProjection) {
            $this->radius->syncService($service);
        }

        $disconnect = ['attempted' => 0, 'succeeded' => 0, 'failed' => 0, 'errors' => []];
        if ($policy->disconnect_on_suspend && $hasOnlineSession) {
            $disconnect = $this->coa->disconnectAllForService($service, $actorUserId);
        }

        $this->event(
            $run,
            $service,
            $invoice,
            null,
            'suspension_enforced',
            $disconnect['failed'] === 0,
            sprintf('Isolir ditegakkan ulang. Disconnect: %d berhasil, %d gagal.', $disconnect['succeeded'], $disconnect['failed']),
            ['source' => $source, 'projection_repaired' => (! $hasRejectProjection || $hasLeakedActiveProjection), 'disconnect' => $disconnect]
        );

        return ['action' => 'enforced', 'invoice_id' => $invoice->id, 'message' => 'Isolir ditegakkan ulang.'];
    }

    /** @return array{action:string,invoice_id:?int,message:string} */
    private function reactivate(
        CustomerService $service,
        ServiceSuspension $suspension,
        AutomationRun $run,
        string $source,
        ?int $actorUserId,
        ?int $paymentId,
    ): array {
        DB::transaction(function () use ($service, $suspension, $run, $source, $actorUserId, $paymentId) {
            $locked = CustomerService::query()->whereKey($service->id)->lockForUpdate()->firstOrFail();
            $lockedSuspension = ServiceSuspension::query()->whereKey($suspension->id)->lockForUpdate()->firstOrFail();

            // Recheck inside the transaction so a concurrent invoice/payment cannot create a false reactivation.
            if ($this->blockingInvoice($locked) !== null) {
                throw new \RuntimeException('Reaktivasi dibatalkan karena masih ada invoice blocking.');
            }

            if ($locked->status === 'suspended') {
                $locked->forceFill(['status' => 'active'])->save();
                ServiceStatusHistory::create([
                    'customer_service_id' => $locked->id,
                    'from_status' => 'suspended',
                    'to_status' => 'active',
                    'reason' => 'Auto reaktivasi: tagihan blocking sudah diselesaikan.',
                    'actor_user_id' => $actorUserId,
                    'metadata' => [
                        'automation_run_id' => $run->id,
                        'suspension_id' => $lockedSuspension->id,
                        'payment_id' => $paymentId,
                        'source' => $source,
                    ],
                ]);
            }

            $lockedSuspension->forceFill([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by_payment_id' => $paymentId,
                'metadata' => [...($lockedSuspension->metadata ?? []), 'resolved_source' => $source, 'resolved_run_id' => $run->id],
            ])->save();

            // Restore Cleartext-Password + reply attributes. If this fails, the DB transaction rolls back.
            $this->radius->syncService($locked->fresh(['plan', 'ipPool']));
        }, 3);

        $this->event(
            $run,
            $service,
            $suspension->invoice,
            $paymentId,
            'service_reactivated',
            true,
            'Layanan otomatis aktif kembali dan projection RADIUS dipulihkan.',
            ['source' => $source, 'suspension_id' => $suspension->id]
        );

        return ['action' => 'reactivated', 'invoice_id' => $suspension->invoice_id, 'message' => 'Layanan diaktifkan kembali otomatis.'];
    }

    private function event(
        AutomationRun $run,
        CustomerService $service,
        ?Invoice $invoice,
        ?int $paymentId,
        string $event,
        bool $success,
        string $message,
        array $metadata = [],
    ): AutomationEvent {
        return AutomationEvent::create([
            'automation_run_id' => $run->id,
            'customer_service_id' => $service->id,
            'invoice_id' => $invoice?->id,
            'payment_id' => $paymentId,
            'event' => $event,
            'success' => $success,
            'message' => $message,
            'metadata' => $metadata,
        ]);
    }

    private function safeEvent(
        AutomationRun $run,
        CustomerService $service,
        ?Invoice $invoice,
        ?int $paymentId,
        string $event,
        bool $success,
        string $message,
        array $metadata = [],
    ): void {
        try {
            $this->event($run, $service, $invoice, $paymentId, $event, $success, $message, $metadata);
        } catch (Throwable) {
            // Preserve the original automation error even if audit logging is unavailable.
        }
    }
}
