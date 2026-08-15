<?php
namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\InternetPlan;
use App\Models\ServiceStatusHistory;
use App\Models\SupportTicket;
use App\Services\RadiusProjectionService;
use App\Services\SaasPlanService;
use App\Services\TenantSequenceService;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PartnerCustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $a=$request->attributes->get('partner_account');
        $q=trim((string)$request->string('q'));
        return Inertia::render('Partner/Customers',[
            'customers'=>Customer::query()->where('partner_id',$a->partner_id)->withCount('services')->when($q!=='',fn($x)=>$x->where(fn($z)=>$z->where('name','ilike','%'.$q.'%')->orWhere('customer_number','ilike','%'.$q.'%')->orWhere('phone','ilike','%'.$q.'%')))->latest()->paginate(15)->withQueryString(),
            'filters'=>['q'=>$q],
            'plans'=>InternetPlan::query()->where('active',true)->orderBy('name')->get(['id','name','code','price']),
        ]);
    }

    public function store(Request $request,TenantSequenceService $seq,SaasPlanService $plans,RadiusProjectionService $radius): RedirectResponse
    {
        $a=$request->attributes->get('partner_account');
        abort_unless(in_array($a->role,['owner','admin','sales'],true),403);
        $tenant=app(CurrentTenant::class)->tenant;
        $tenantId=(string)$tenant->id;
        $data=$request->validate([
            'name'=>['required','string','max:160'],
            'email'=>['nullable','email','max:190'],
            'phone'=>['required','string','max:40'],
            'address_line'=>['required','string','max:1000'],
            'city'=>['nullable','string','max:120'],
            'notes'=>['nullable','string','max:1000'],
            'internet_plan_id'=>['nullable',Rule::exists('internet_plans','id')->where(fn($q)=>$q->where('tenant_id',$tenantId)->where('active',true))],
        ]);

        [$customer,$service]=DB::transaction(function()use($a,$tenant,$tenantId,$data,$seq,$plans){
            DB::select('select pg_advisory_xact_lock(hashtext(?))',["jaringanku:quota:{$tenantId}:customers"]);
            $plans->assertCanCreate($tenant,'customers');
            if(!empty($data['internet_plan_id'])){
                DB::select('select pg_advisory_xact_lock(hashtext(?))',["jaringanku:quota:{$tenantId}:services"]);
                $plans->assertCanCreate($tenant,'services');
            }
            $number=$seq->next($tenantId,'customer','JRG-');
            $customer=Customer::create(['partner_id'=>$a->partner_id,'created_by_partner_account_id'=>$a->id,'customer_number'=>$number,'name'=>$data['name'],'customer_type'=>'residential','email'=>$data['email']??null,'phone'=>$data['phone'],'status'=>'active','notes'=>$data['notes']??'Dibuat melalui Portal Mitra']);
            $customer->addresses()->create(['label'=>'Instalasi','address_line'=>$data['address_line'],'city'=>$data['city']??null,'is_primary'=>true]);
            $service=null;
            if(!empty($data['internet_plan_id'])){
                $service=$customer->services()->create([
                    'internet_plan_id'=>$data['internet_plan_id'],'service_number'=>$seq->next($tenantId,'service','SRV-'),'service_type'=>'pppoe',
                    'pppoe_username'=>'mtr-'.strtolower(str_replace(['JRG-','_'],['','-'],$number)),'pppoe_password'=>Str::password(16),
                    'status'=>'pending_installation','billing_day'=>1,'due_day'=>10,'notes'=>'Service request dibuat Portal Mitra.',
                ]);
                ServiceStatusHistory::create(['customer_service_id'=>$service->id,'from_status'=>null,'to_status'=>'pending_installation','reason'=>'Request instalasi dari Portal Mitra','actor_user_id'=>null]);
                SupportTicket::create(['ticket_number'=>$seq->next($tenantId,'ticket','TCK-'),'customer_id'=>$customer->id,'customer_service_id'=>$service->id,'created_by_partner_account_id'=>$a->id,'source'=>'partner','category'=>'installation','priority'=>'normal','status'=>'open','subject'=>'Request instalasi '.$number,'description'=>'Pelanggan baru dari mitra meminta instalasi paket yang dipilih.','opened_at'=>now()]);
            }
            return [$customer,$service];
        },3);
        if($service)$radius->syncService($service);
        return back()->with('success','Pelanggan '.$customer->customer_number.' berhasil didaftarkan'.($service?' dan request instalasi dibuat.':'.'));
    }
}
