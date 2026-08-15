<?php

namespace App\Console\Commands;

use App\Models\PaymentGatewayEvent;
use App\Models\PaymentGatewaySetting;
use App\Models\PaymentGatewayTransaction;
use App\Models\WhatsAppMessageLog;
use App\Models\WhatsAppSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class Phase09PreflightCommand extends Command
{
    protected $signature = 'jaringanku:phase09-preflight';

    protected $description = 'Validate Phase 09 database tables and Eloquent table mappings before seeding.';

    public function handle(): int
    {
        $tables = [
            'payment_gateway_settings',
            'payment_gateway_transactions',
            'payment_gateway_events',
            'whatsapp_settings',
            'whatsapp_message_logs',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Phase 09 table missing: {$table}");
                return self::FAILURE;
            }
        }

        $modelTables = [
            PaymentGatewaySetting::class => 'payment_gateway_settings',
            PaymentGatewayTransaction::class => 'payment_gateway_transactions',
            PaymentGatewayEvent::class => 'payment_gateway_events',
            WhatsAppSetting::class => 'whatsapp_settings',
            WhatsAppMessageLog::class => 'whatsapp_message_logs',
        ];

        foreach ($modelTables as $modelClass => $expectedTable) {
            $actualTable = (new $modelClass())->getTable();
            if ($actualTable !== $expectedTable) {
                $this->error("Model table mismatch: {$modelClass} => {$actualTable}; expected {$expectedTable}");
                return self::FAILURE;
            }
        }

        $this->info('PHASE 09 DATABASE PREFLIGHT PASSED');
        return self::SUCCESS;
    }
}
