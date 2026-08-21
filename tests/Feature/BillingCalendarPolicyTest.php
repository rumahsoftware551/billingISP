<?php

namespace Tests\Feature;

use App\Models\CustomerService;
use App\Services\BillingCalendar;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class BillingCalendarPolicyTest extends TestCase
{
    public function test_due_day_before_billing_day_rolls_to_next_month(): void
    {
        $service = new CustomerService(['billing_day' => 20, 'due_day' => 10]);
        $dates = app(BillingCalendar::class)->datesForService($service, CarbonImmutable::parse('2026-08-01'));

        $this->assertSame('2026-08-20', $dates['issued_at']->toDateString());
        $this->assertSame('2026-09-10', $dates['due_at']->toDateString());
    }

    public function test_days_are_clamped_to_supported_safe_range(): void
    {
        $calendar = app(BillingCalendar::class);
        $this->assertSame(1, $calendar->normalizeDay(0));
        $this->assertSame(28, $calendar->normalizeDay(31));
    }
}
