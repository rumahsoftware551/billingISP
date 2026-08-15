<?php
namespace App\Http\Controllers\Partner;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Invoice;
use App\Models\PartnerCommissionEntry;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
class PartnerDashboardController extends Controller {
    public function __invoke(Request $request):Response {
        $a=$request->attributes->get('partner_account');$pid=$a->partner_id;
        $customerIds=Customer::query()->where('partner_id',$pid)->pluck('id');
        return Inertia::render('Partner/Dashboard',[
            'stats'=>[
                'customers'=>$customerIds->count(),
                'active_services'=>CustomerService::query()->whereIn('customer_id',$customerIds)->where('status','active')->count(),
                'suspended_services'=>CustomerService::query()->whereIn('customer_id',$customerIds)->where('status','suspended')->count(),
                'outstanding'=>(int)Invoice::query()->whereIn('customer_id',$customerIds)->whereIn('status',['unpaid','partial','overdue'])->sum('balance_due'),
                'payments_month'=>(int)Payment::query()->whereIn('customer_id',$customerIds)->where('status','posted')->whereBetween('paid_at',[now()->startOfMonth(),now()->endOfMonth()])->sum('amount'),
                'commission_available'=>(int)PartnerCommissionEntry::query()->where('partner_id',$pid)->where('status','available')->sum('amount'),
            ],
            'recentCustomers'=>Customer::query()->where('partner_id',$pid)->latest()->limit(8)->get(['id','customer_number','name','phone','status','created_at']),
            'recentCommissions'=>PartnerCommissionEntry::query()->where('partner_id',$pid)->latest('earned_at')->limit(8)->get(['id','entry_type','amount','status','earned_at']),
        ]);
    }
}
