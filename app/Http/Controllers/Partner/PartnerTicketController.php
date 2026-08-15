<?php
namespace App\Http\Controllers\Partner;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Services\TenantSequenceService;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
class PartnerTicketController extends Controller {
    public function index(Request $request):Response { $a=$request->attributes->get('partner_account');return Inertia::render('Partner/Tickets',[
        'tickets'=>SupportTicket::query()->where('created_by_partner_account_id',$a->id)->with('customer:id,customer_number,name,partner_id')->latest()->paginate(20),
        'customers'=>Customer::query()->where('partner_id',$a->partner_id)->orderBy('name')->get(['id','customer_number','name']),
    ]);}
    public function store(Request $request,TenantSequenceService $seq):RedirectResponse { $a=$request->attributes->get('partner_account');$tenantId=app(CurrentTenant::class)->id();$data=$request->validate(['customer_id'=>['required',Rule::exists('customers','id')->where(fn($q)=>$q->where('tenant_id',$tenantId)->where('partner_id',$a->partner_id))],'category'=>['required',Rule::in(['installation','technical','billing','other'])],'priority'=>['required',Rule::in(['low','normal','high','urgent'])],'subject'=>['required','string','max:200'],'description'=>['required','string','max:3000']]);SupportTicket::create(['ticket_number'=>$seq->next($tenantId,'ticket','TCK-'),'customer_id'=>$data['customer_id'],'created_by_partner_account_id'=>$a->id,'source'=>'partner','category'=>$data['category'],'priority'=>$data['priority'],'status'=>'open','subject'=>$data['subject'],'description'=>$data['description'],'opened_at'=>now()]);return back()->with('success','Request/ticket mitra berhasil dibuat.');}
}
