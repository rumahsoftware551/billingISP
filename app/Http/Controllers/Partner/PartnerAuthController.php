<?php
namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\PartnerAccount;
use App\Models\PartnerLoginEvent;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PartnerAuthController extends Controller
{
    public function create(string $tenantSlug): Response
    {
        $tenant=Tenant::query()->where('slug',$tenantSlug)->where('status','active')->firstOrFail();
        app()->instance(CurrentTenant::class,new CurrentTenant($tenant));
        return Inertia::render('Partner/Auth/Login',['portalTenant'=>$tenant->only('id','name','slug')]);
    }
    public function store(Request $request,string $tenantSlug): RedirectResponse
    {
        $tenant=Tenant::query()->where('slug',$tenantSlug)->where('status','active')->firstOrFail();
        app()->instance(CurrentTenant::class,new CurrentTenant($tenant));
        $data=$request->validate(['email'=>['required','email','max:190'],'password'=>['required','string','max:200']]);
        $email=Str::lower(trim($data['email']));
        $key='partner-login|'.$tenant->id.'|'.$email.'|'.$request->ip();
        if(RateLimiter::tooManyAttempts($key,5)){
            $this->event($tenant->id,null,null,'rate_limited',$request,['email_hash'=>hash('sha256',$email)]);
            throw ValidationException::withMessages(['email'=>'Terlalu banyak percobaan login. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.']);
        }
        $account=PartnerAccount::query()->with('partner')->whereRaw('LOWER(email)=?',[$email])->where('status','active')->first();
        if(!$account || !$account->partner || $account->partner->status!=='active' || !$account->passwordMatches($data['password'])){
            RateLimiter::hit($key,120);$this->event($tenant->id,$account?->partner_id,$account?->id,'failed',$request,['email_hash'=>hash('sha256',$email)]);
            throw ValidationException::withMessages(['email'=>'Email atau password mitra salah.']);
        }
        RateLimiter::clear($key);$request->session()->regenerate();
        $request->session()->put('partner_account_id',$account->id);$request->session()->put('partner_tenant_id',(string)$tenant->id);
        $account->forceFill(['last_login_at'=>now(),'last_login_ip'=>$request->ip()])->save();
        $this->event($tenant->id,$account->partner_id,$account->id,'login',$request);
        return redirect()->intended(route('partner.dashboard',['tenantSlug'=>$tenantSlug]));
    }
    public function destroy(Request $request,string $tenantSlug): RedirectResponse
    {
        $accountId=(int)$request->session()->get('partner_account_id',0);$tenantId=(string)$request->session()->get('partner_tenant_id','');
        $this->event($tenantId,null,$accountId?:null,'logout',$request);
        $request->session()->invalidate();$request->session()->regenerateToken();
        return redirect()->route('partner.login',['tenantSlug'=>$tenantSlug]);
    }
    private function event(string $tenantId,?int $partnerId,?int $accountId,string $event,Request $request,array $meta=[]):void
    {
        if($tenantId==='')return;
        PartnerLoginEvent::query()->create(['tenant_id'=>$tenantId,'partner_id'=>$partnerId,'partner_account_id'=>$accountId,'event'=>$event,'ip_address'=>$request->ip(),'user_agent'=>Str::limit((string)$request->userAgent(),500,''),'meta'=>$meta?:null,'created_at'=>now()]);
    }
}
