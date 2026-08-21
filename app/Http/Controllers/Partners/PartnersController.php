<?php
namespace App\Http\Controllers\Partners;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Partner;
use App\Models\PartnerAccount;
use App\Models\PartnerCommissionRule;
use App\Models\PartnerWithdrawal;
use App\Services\TenantSequenceService;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
class PartnersController extends Controller {
    public function index():Response {return Inertia::render('Partners/Index',[
        'partners'=>Partner::query()->withCount(['customers','accounts'])->withSum(['commissions as commission_available'=>fn($q)=>$q->where('status','available')],'amount')->orderBy('name')->get(),
        'unassignedCustomers'=>Customer::query()->whereNull('partner_id')->orderBy('name')->limit(100)->get(['id','customer_number','name']),
        'withdrawals'=>PartnerWithdrawal::query()->with('partner:id,name')->latest('requested_at')->limit(30)->get(),
    ]);}
    public function store(Request $request,TenantSequenceService $seq):RedirectResponse { $tenantId=app(CurrentTenant::class)->id();$data=$request->validate(['name'=>['required','string','max:160'],'email'=>['nullable','email','max:190'],'phone'=>['nullable','string','max:40'],'area_name'=>['nullable','string','max:160'],'address'=>['nullable','string','max:1000'],'bank_name'=>['nullable','string','max:80'],'bank_account'=>['nullable','string','max:100'],'bank_holder'=>['nullable','string','max:160']]);$partner=Partner::create(['code'=>$seq->next($tenantId,'partner','MTR-',5),'name'=>$data['name'],'status'=>'active','email'=>$data['email']??null,'phone'=>$data['phone']??null,'area_name'=>$data['area_name']??null,'address'=>$data['address']??null,'payout_account'=>array_filter(['bank'=>$data['bank_name']??null,'account'=>$data['bank_account']??null,'holder'=>$data['bank_holder']??null])]);return back()->with('success','Mitra '.$partner->code.' berhasil dibuat.');}
    public function account(Request $request,Partner $partner):RedirectResponse { $this->ensureTenantOwnership($partner);$request->merge(['email'=>strtolower(trim((string)$request->input('email')))]);$data=$request->validate(['name'=>['required','string','max:160'],'email'=>['required','email','max:190',Rule::unique('partner_accounts','email')->where(fn($q)=>$q->where('tenant_id',app(CurrentTenant::class)->id()))],'role'=>['required',Rule::in(['owner','admin','collector','sales'])],'password'=>['required','string','min:10','max:190']]);PartnerAccount::create(['partner_id'=>$partner->id,'name'=>$data['name'],'email'=>strtolower($data['email']),'role'=>$data['role'],'password'=>$data['password'],'status'=>'active','must_change_password'=>false]);return back()->with('success','Akun portal mitra berhasil dibuat.');}
    public function rule(Request $request,Partner $partner):RedirectResponse { $this->ensureTenantOwnership($partner);$data=$request->validate(['name'=>['required','string','max:120'],'type'=>['required',Rule::in(['payment_percent','payment_fixed','activation_fixed','active_customer_fixed'])],'value'=>['required','integer','min:1'],'active'=>['nullable','boolean']]);if($data['type']==='payment_percent' && (int)$data['value']>10000) throw \Illuminate\Validation\ValidationException::withMessages(['value'=>'Komisi persentase maksimal 100% (10000 basis points).']);PartnerCommissionRule::create(['partner_id'=>$partner->id,...$data,'active'=>$request->boolean('active',true)]);return back()->with('success','Aturan komisi mitra ditambahkan.');}
    public function assign(Request $request,Partner $partner):RedirectResponse { $this->ensureTenantOwnership($partner);$tenantId=app(CurrentTenant::class)->id();$data=$request->validate(['customer_id'=>['required',Rule::exists('customers','id')->where(fn($q)=>$q->where('tenant_id',$tenantId))]]);Customer::query()->whereKey($data['customer_id'])->update(['partner_id'=>$partner->id]);return back()->with('success','Pelanggan berhasil ditetapkan ke mitra.');}
    public function withdrawal(Request $request,PartnerWithdrawal $withdrawal):RedirectResponse {
        $this->ensureTenantOwnership($withdrawal);
        $data=$request->validate(['status'=>['required',Rule::in(['approved','paid','rejected'])],'notes'=>['nullable','string','max:1000']]);
        \Illuminate\Support\Facades\DB::transaction(function()use($withdrawal,$data){
            $locked=PartnerWithdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            $allowed=['requested'=>['approved','rejected'],'approved'=>['paid','rejected'],'paid'=>[],'rejected'=>[]];
            if($data['status']!==$locked->status && !in_array($data['status'],$allowed[$locked->status]??[],true)) throw \Illuminate\Validation\ValidationException::withMessages(['status'=>'Transisi status withdrawal tidak valid.']);
            if($data['status']==='paid' && $locked->status!=='paid'){
                $remaining=(int)$locked->amount;
                $entries=$locked->partner->commissions()->where('status','available')->oldest('earned_at')->lockForUpdate()->get();
                if((int)$entries->sum('amount')<$remaining) throw \Illuminate\Validation\ValidationException::withMessages(['status'=>'Saldo komisi tersedia tidak mencukupi untuk menandai withdrawal sebagai paid.']);
                foreach($entries as $entry){
                    if($remaining<=0)break;
                    if((int)$entry->amount<=$remaining){
                        $meta=(array)($entry->meta??[]);$meta['withdrawal_id']=$locked->id;
                        $entry->forceFill(['status'=>'paid','paid_at'=>now(),'meta'=>$meta])->save();$remaining-=(int)$entry->amount;
                    }else{
                        $consume=$remaining;
                        $entry->forceFill(['amount'=>(int)$entry->amount-$consume])->save();
                        \App\Models\PartnerCommissionEntry::query()->create([
                            'partner_id'=>$entry->partner_id,'partner_commission_rule_id'=>$entry->partner_commission_rule_id,'customer_id'=>$entry->customer_id,'invoice_id'=>$entry->invoice_id,'payment_id'=>$entry->payment_id,
                            'entry_type'=>$entry->entry_type,'basis_amount'=>$entry->basis_amount,'amount'=>$consume,'status'=>'paid','idempotency_key'=>'withdrawal:'.$locked->id.':entry:'.$entry->id,
                            'earned_at'=>$entry->earned_at,'paid_at'=>now(),'meta'=>array_merge((array)($entry->meta??[]),['withdrawal_id'=>$locked->id,'split_from_entry_id'=>$entry->id]),
                        ]);
                        $remaining=0;
                    }
                }
            }
            $locked->forceFill(['status'=>$data['status'],'notes'=>$data['notes']??$locked->notes,'processed_at'=>now(),'processed_by'=>auth()->id()])->save();
        },3);
        return back()->with('success','Status withdrawal diperbarui.');
    }
}
