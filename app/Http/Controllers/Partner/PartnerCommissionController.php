<?php
namespace App\Http\Controllers\Partner;
use App\Http\Controllers\Controller;
use App\Models\PartnerCommissionEntry;
use App\Models\PartnerWithdrawal;
use App\Services\PartnerCommissionService;
use App\Services\TenantSequenceService;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
class PartnerCommissionController extends Controller {
    public function index(Request $request,PartnerCommissionService $service):Response { $a=$request->attributes->get('partner_account');abort_unless(in_array($a->role,['owner','admin'],true),403);return Inertia::render('Partner/Commissions',[
        'balance'=>$service->availableBalance($a->partner_id),
        'entries'=>PartnerCommissionEntry::query()->where('partner_id',$a->partner_id)->with('customer:id,customer_number,name')->latest('earned_at')->paginate(20),
        'withdrawals'=>PartnerWithdrawal::query()->where('partner_id',$a->partner_id)->latest('requested_at')->limit(20)->get(),
        'payoutAccount'=>$a->partner->payout_account,
    ]); }
    public function requestWithdrawal(Request $request,TenantSequenceService $seq,PartnerCommissionService $service):RedirectResponse { $a=$request->attributes->get('partner_account');abort_unless(in_array($a->role,['owner','admin'],true),403);$data=$request->validate(['amount'=>['required','integer','min:1000'],'notes'=>['nullable','string','max:1000']]);$available=$service->availableBalance($a->partner_id);if(empty($a->partner->payout_account))return back()->with('error','Rekening payout mitra belum dikonfigurasi oleh ISP.');if((int)$data['amount']>$available)return back()->with('error','Nominal withdrawal melebihi saldo komisi tersedia.');
        DB::transaction(function()use($a,$data,$seq){PartnerWithdrawal::create(['partner_id'=>$a->partner_id,'partner_account_id'=>$a->id,'withdrawal_number'=>$seq->next(app(CurrentTenant::class)->id(),'partner-withdrawal:'.now()->format('Ym'),'WD-'.now()->format('Ym').'-',5),'amount'=>$data['amount'],'status'=>'requested','payout_account'=>$a->partner->payout_account,'notes'=>$data['notes']??null,'requested_at'=>now()]);},3);
        return back()->with('success','Permintaan withdrawal berhasil dikirim untuk persetujuan ISP.'); }
}
