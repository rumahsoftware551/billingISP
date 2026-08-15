<?php
namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\PartnerAccount;
use App\Models\PartnerCommissionEntry;
use App\Models\PartnerCommissionRule;
use App\Models\PartnerWithdrawal;
use App\Models\Tenant;
use App\Services\PartnerCommissionService;
use App\Services\PaymentService;
use App\Support\CurrentTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Phase13SmokeCommand extends Command
{
    protected $signature='jaringanku:phase13-smoke';
    protected $description='Validate Partner Portal scoping, payment attribution, commission idempotency, and withdrawal reservation.';
    public function handle():int
    {
        $tenant=Tenant::query()->where('slug',config('jaringanku.seed_tenant_slug','demo-isp'))->first();
        if(!$tenant){$this->error('Demo tenant tidak ditemukan.');return self::FAILURE;}
        app()->instance(CurrentTenant::class,new CurrentTenant($tenant));
        $demo=PartnerAccount::query()->where('email','mitra@jaringanku.local')->first();
        if(!$demo && !filter_var(env('SEED_DEMO_DATA',false), FILTER_VALIDATE_BOOL)){$this->warn('SEED_DEMO_DATA=false; Phase 13 runtime smoke dilewati setelah preflight.');return self::SUCCESS;}
        if(!$demo || !$demo->passwordMatches(env('PHASE13_PARTNER_PASSWORD','MitraDemo123!'))){$this->error('Demo Partner account/password belum valid.');return 2;}

        DB::beginTransaction();
        try{
            $suffix=Str::upper(Str::random(8));
            $partner=Partner::query()->create(['code'=>'SMOKE-'.$suffix,'name'=>'Phase 13 Smoke Partner','status'=>'active','payout_account'=>['bank'=>'TEST','account'=>'00013','holder'=>'Smoke Partner']]);
            $account=PartnerAccount::query()->create(['partner_id'=>$partner->id,'name'=>'Smoke Owner','email'=>'smoke-'.$suffix.'@jaringanku.local','password'=>'SmokePartner123!','role'=>'owner','status'=>'active','must_change_password'=>false]);
            $rule=PartnerCommissionRule::query()->create(['partner_id'=>$partner->id,'name'=>'Smoke 10%','type'=>'payment_percent','value'=>1000,'active'=>true]);

            $this->info('1/4 Memverifikasi partner/customer tenant scope...');
            $customer=Customer::query()->create(['partner_id'=>$partner->id,'customer_number'=>'SMOKE-MTR-'.$suffix,'name'=>'Phase 13 Smoke Customer','customer_type'=>'residential','phone'=>'081300000013','status'=>'active']);
            if((int)$customer->partner_id!==(int)$partner->id){$this->error('Partner assignment gagal.');return 3;}

            $this->info('2/4 Mem-post pembayaran dan memverifikasi attribution mitra...');
            $invoice=Invoice::query()->create(['customer_id'=>$customer->id,'invoice_number'=>'SMOKE-INV-'.$suffix,'billing_key'=>'smoke-partner-'.Str::uuid(),'period_start'=>now()->startOfMonth(),'period_end'=>now()->endOfMonth(),'issued_at'=>now()->toDateString(),'due_at'=>now()->addDays(7)->toDateString(),'subtotal'=>10000,'discount'=>0,'tax'=>0,'total'=>10000,'paid_amount'=>0,'balance_due'=>10000,'status'=>'unpaid']);
            $payment=app(PaymentService::class)->postToInvoice($invoice,10000,'cash','PH13-SMOKE',now(),'Phase 13 partner smoke.',null,$partner->id,$account->id);
            if((int)$payment->partner_id!==(int)$partner->id || (int)$payment->partner_account_id!==(int)$account->id){$this->error('Payment attribution mitra gagal.');return 4;}

            $this->info('3/4 Memverifikasi commission ledger idempotent...');
            $entries=PartnerCommissionEntry::query()->where('payment_id',$payment->id)->get();
            if($entries->count()!==1 || (int)$entries->first()->amount!==1000){$this->error('Komisi pembayaran 10% tidak sesuai.');return 5;}
            app(PartnerCommissionService::class)->accrueForPayment($payment->fresh(['customer.partner','allocations.invoice']));
            if(PartnerCommissionEntry::query()->where('payment_id',$payment->id)->count()!==1){$this->error('Commission idempotency gagal; entry duplikat terbentuk.');return 6;}

            $this->info('4/4 Memverifikasi withdrawal reservation...');
            $before=app(PartnerCommissionService::class)->availableBalance($partner->id);
            $withdrawal=PartnerWithdrawal::query()->create(['partner_id'=>$partner->id,'partner_account_id'=>$account->id,'withdrawal_number'=>'SMOKE-WD-'.$suffix,'amount'=>$before,'status'=>'requested','payout_account'=>$partner->payout_account,'requested_at'=>now()]);
            if($before!==1000 || app(PartnerCommissionService::class)->availableBalance($partner->id)!==0 || (int)$withdrawal->partner_id!==(int)$partner->id){$this->error('Withdrawal reservation balance gagal.');return 7;}

            $this->newLine();$this->info('PHASE 13 PARTNER PORTAL SMOKE TEST PASSED');
            $this->line('Partner account/hash       : PASS');
            $this->line('Customer partner scope     : PASS');
            $this->line('Payment attribution        : PASS');
            $this->line('Commission 10% + idempotent: PASS');
            $this->line('Withdrawal reservation     : PASS');
            return self::SUCCESS;
        } finally { DB::rollBack(); }
    }
}
