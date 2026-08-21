<?php

namespace App\Services;

use App\Models\CustomerService;
use App\Models\Invoice;
use Carbon\CarbonImmutable;

class BillingCalendar
{
    public function normalizeDay(int|string|null $day): int
    {
        return max(1, min(28, (int) ($day ?: 1)));
    }

    /** @return array{issued_at:CarbonImmutable,due_at:CarbonImmutable} */
    public function datesForService(CustomerService $service, CarbonImmutable $periodStart): array
    {
        $periodStart = $periodStart->startOfMonth();
        $issueDay = $this->normalizeDay($service->billing_day);
        $dueDay = $this->normalizeDay($service->due_day);

        $issuedAt = $periodStart->setDay($issueDay);
        $dueAt = $dueDay >= $issueDay
            ? $periodStart->setDay($dueDay)
            : $periodStart->addMonthNoOverflow()->setDay($dueDay);

        return ['issued_at' => $issuedAt, 'due_at' => $dueAt];
    }

    public function blockingCutoff(CarbonImmutable $asOf, int $graceDays): CarbonImmutable
    {
        return $asOf->startOfDay()->subDays(max(0, $graceDays));
    }

    public function invoiceIsPastGrace(Invoice $invoice, int $graceDays, CarbonImmutable $asOf): bool
    {
        if (! $invoice->due_at) {
            return false;
        }

        return $invoice->due_at->startOfDay()->isBefore($this->blockingCutoff($asOf, $graceDays));
    }
}
