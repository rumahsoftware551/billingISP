<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CommercialFinanceGateTest extends TestCase
{
    public function test_commercial_finance_commands_and_routes_are_registered(): void
    {
        $commands = array_keys(Artisan::all());
        $this->assertContains('jaringanku:billing-due-run', $commands);
        $this->assertContains('jaringanku:payment-reconcile', $commands);
        $this->assertContains('jaringanku:automation-run', $commands);
        $this->assertTrue(Route::has('billing.manual-payments.review'));
        $this->assertTrue(Route::has('billing.invoices.download'));
        $this->assertTrue(Route::has('billing.payments.receipt'));
    }
}
