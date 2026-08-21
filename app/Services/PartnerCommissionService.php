<?php
namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\PartnerCommissionEntry;
use App\Models\PartnerCommissionRule;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class PartnerCommissionService
{
    public function accrueForPayment(Payment $payment): array
    {
        $payment->loadMissing(['customer.partner','allocations.invoice']);
        $partner = $payment->customer?->partner;
        if (! $partner || $partner->status !== 'active') return [];

        $rules = $this->activeRules($partner->id, ['payment_percent','payment_fixed']);
        $invoice = $payment->allocations->first()?->invoice;
        $created=[];
        DB::transaction(function () use ($payment,$partner,$rules,$invoice,&$created) {
            foreach ($rules as $rule) {
                $amount = $rule->type === 'payment_percent'
                    ? intdiv(((int)$payment->amount) * ((int)$rule->value), 10000)
                    : (int)$rule->value;
                if ($amount <= 0) continue;
                $key = 'payment:'.$payment->id.':rule:'.$rule->id;
                $created[] = PartnerCommissionEntry::query()->firstOrCreate(
                    ['idempotency_key'=>$key],
                    [
                        'partner_id'=>$partner->id,
                        'partner_commission_rule_id'=>$rule->id,
                        'customer_id'=>$payment->customer_id,
                        'invoice_id'=>$invoice?->id,
                        'payment_id'=>$payment->id,
                        'entry_type'=>$rule->type,
                        'basis_amount'=>$payment->amount,
                        'amount'=>$amount,
                        'status'=>'available',
                        'earned_at'=>$payment->paid_at ?: now(),
                        'meta'=>['payment_number'=>$payment->payment_number],
                    ]
                );
            }
        },3);
        return $created;
    }

    public function accrueActivationForService(CustomerService $service): array
    {
        $service->loadMissing('customer.partner');
        $partner=$service->customer?->partner;
        if(!$partner || $partner->status!=='active' || $service->status!=='active') return [];
        $rules=$this->activeRules($partner->id,['activation_fixed']);
        $created=[];
        DB::transaction(function()use($service,$partner,$rules,&$created){
            foreach($rules as $rule){
                $key='activation:service:'.$service->id.':rule:'.$rule->id;
                $created[]=PartnerCommissionEntry::query()->firstOrCreate(
                    ['idempotency_key'=>$key],
                    [
                        'partner_id'=>$partner->id,'partner_commission_rule_id'=>$rule->id,'customer_id'=>$service->customer_id,'invoice_id'=>null,'payment_id'=>null,
                        'entry_type'=>'activation_fixed','basis_amount'=>1,'amount'=>(int)$rule->value,'status'=>'available','earned_at'=>now(),
                        'meta'=>['service_id'=>$service->id,'service_number'=>$service->service_number],
                    ]
                );
            }
        },3);
        return $created;
    }

    public function accrueMonthlyActiveCustomers(?\Carbon\CarbonImmutable $period=null): int
    {
        $period=$period ?: now()->toImmutable()->startOfMonth();
        $periodKey=$period->format('Ym');
        $rules=PartnerCommissionRule::query()->where('active',true)->where('type','active_customer_fixed')->whereHas('partner',fn($q)=>$q->where('status','active'))
            ->where(fn($q)=>$q->whereNull('starts_at')->orWhere('starts_at','<=',$period->endOfMonth()->toDateString()))
            ->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>=',$period->startOfMonth()->toDateString()))->get();
        $count=0;
        foreach($rules as $rule){
            $customerIds=Customer::query()->where('partner_id',$rule->partner_id)->where('status','active')
                ->whereHas('services',fn($q)=>$q->where('status','active'))->pluck('id');
            foreach($customerIds as $customerId){
                $key='active-customer:'.$periodKey.':customer:'.$customerId.':rule:'.$rule->id;
                $entry=PartnerCommissionEntry::query()->firstOrCreate(
                    ['idempotency_key'=>$key],
                    ['partner_id'=>$rule->partner_id,'partner_commission_rule_id'=>$rule->id,'customer_id'=>$customerId,'entry_type'=>'active_customer_fixed','basis_amount'=>1,'amount'=>(int)$rule->value,'status'=>'available','earned_at'=>$period->endOfMonth(),'meta'=>['period'=>$period->format('Y-m')]]
                );
                if($entry->wasRecentlyCreated)$count++;
            }
        }
        return $count;
    }

    public function availableBalance(int $partnerId): int
    {
        $gross=(int) PartnerCommissionEntry::query()->where('partner_id',$partnerId)->where('status','available')->sum('amount');
        $reserved=(int) \App\Models\PartnerWithdrawal::query()->where('partner_id',$partnerId)->whereIn('status',['requested','approved'])->sum('amount');
        return max(0,$gross-$reserved);
    }

    private function activeRules(int $partnerId,array $types)
    {
        return PartnerCommissionRule::query()->where('partner_id',$partnerId)->where('active',true)->whereIn('type',$types)
            ->where(fn($q)=>$q->whereNull('starts_at')->orWhere('starts_at','<=',now()->toDateString()))
            ->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>=',now()->toDateString()))->get();
    }
}
