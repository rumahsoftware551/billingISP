<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class Phase13PreflightCommand extends Command
{
    protected $signature='jaringanku:phase13-preflight';
    protected $description='Validate Phase 13 Partner Portal schema, model mappings, and routes.';
    public function handle():int
    {
        foreach(['partners','partner_accounts','partner_commission_rules','partner_commission_entries','partner_withdrawals','partner_login_events'] as $table){if(!Schema::hasTable($table)){$this->error("Table {$table} tidak tersedia.");return self::FAILURE;}}
        foreach([['customers','partner_id'],['customers','created_by_partner_account_id'],['payments','partner_id'],['payments','partner_account_id'],['support_tickets','created_by_partner_account_id']] as [$table,$column]){if(!Schema::hasColumn($table,$column)){$this->error("Column {$table}.{$column} tidak tersedia.");return self::FAILURE;}}
        $maps=[[new \App\Models\Partner,'partners'],[new \App\Models\PartnerAccount,'partner_accounts'],[new \App\Models\PartnerCommissionRule,'partner_commission_rules'],[new \App\Models\PartnerCommissionEntry,'partner_commission_entries'],[new \App\Models\PartnerWithdrawal,'partner_withdrawals']];
        foreach($maps as [$model,$expected]){if($model->getTable()!==$expected){$this->error("Model mapping {$expected} tidak sesuai.");return self::FAILURE;}}
        foreach(['partners.index','partner.login','partner.dashboard','partner.customers.index','partner.billing.index','partner.commissions.index','partner.tickets.index'] as $name){if(!Route::has($name)){$this->error("Route {$name} tidak terdaftar.");return self::FAILURE;}}
        $this->info('PHASE 13 PARTNER PORTAL PREFLIGHT PASSED');
        return self::SUCCESS;
    }
}
