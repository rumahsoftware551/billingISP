<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PaymentAllocation;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\DB;

class PaymentReconciliationService
{
    public function __construct(private readonly BillingEngine $billing) {}

    /** @return array{scanned:int,repaired:int,mismatches:int,violations:int} */
    public function reconcileTenant(Tenant $tenant, bool $repair = true): array
    {
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));

        $stats = ['scanned' => 0, 'repaired' => 0, 'mismatches' => 0, 'violations' => 0];

        Invoice::query()->orderBy('id')->chunkById(100, function ($invoices) use (&$stats, $repair) {
            foreach ($invoices as $invoice) {
                $stats['scanned']++;

                DB::transaction(function () use ($invoice, &$stats, $repair) {
                    $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

                    $paidAmount = (int) PaymentAllocation::query()
                        ->where('invoice_id', $locked->id)
                        ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
                        ->where('payments.status', 'posted')
                        ->sum('payment_allocations.amount');

                    if ($paidAmount > (int) $locked->total) {
                        $stats['violations']++;
                        return;
                    }

                    $balance = max(0, (int) $locked->total - $paidAmount);
                    $latestPaidAt = null;
                    if ($balance === 0 && $paidAmount > 0) {
                        $latestPaidAt = PaymentAllocation::query()
                            ->where('invoice_id', $locked->id)
                            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
                            ->where('payments.status', 'posted')
                            ->max('payments.paid_at');
                    }

                    $locked->paid_amount = $paidAmount;
                    $locked->balance_due = $balance;
                    $locked->paid_at = $balance === 0 ? $latestPaidAt : null;
                    $expectedStatus = $this->billing->statusFor($locked);

                    $mismatch = (int) $invoice->paid_amount !== $paidAmount
                        || (int) $invoice->balance_due !== $balance
                        || (string) $invoice->status !== $expectedStatus
                        || (($invoice->paid_at?->toIso8601String() ?? null) !== ($locked->paid_at?->toIso8601String() ?? null));

                    if (! $mismatch) {
                        return;
                    }

                    $stats['mismatches']++;
                    if (! $repair) {
                        return;
                    }

                    $locked->status = $expectedStatus;
                    $locked->save();
                    $stats['repaired']++;
                }, 3);
            }
        });

        return $stats;
    }
}
