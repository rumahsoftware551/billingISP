<?php
namespace App\Console\Commands;
use App\Models\Tenant;
use App\Services\PartnerCommissionService;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
class PartnerMonthlyCommissionCommand extends Command {
    protected $signature='jaringanku:partner-monthly-commission {period? : YYYY-MM}';
    protected $description='Generate idempotent recurring partner commission for active customers.';
    public function handle(PartnerCommissionService $service):int {
        $period=$this->argument('period')?CarbonImmutable::createFromFormat('Y-m',$this->argument('period'))->startOfMonth():now()->toImmutable()->subMonthNoOverflow()->startOfMonth();
        $total=0;
        foreach(Tenant::query()->where('status','active')->get() as $tenant){app()->instance(CurrentTenant::class,new CurrentTenant($tenant));$total+=$service->accrueMonthlyActiveCustomers($period);}
        $this->info("Partner monthly commission {$period->format('Y-m')}: {$total} entry baru.");return self::SUCCESS;
    }
}
