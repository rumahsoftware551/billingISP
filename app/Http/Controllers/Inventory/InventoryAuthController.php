<?php
namespace App\Http\Controllers\Inventory;
use App\Http\Controllers\Controller;
use App\Models\InventoryLoginEvent;
use App\Models\InventoryPortalAccount;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
class InventoryAuthController extends Controller {
    public function create(string $tenantSlug):Response { $tenant=Tenant::query()->where('slug',$tenantSlug)->where('status','active')->firstOrFail(); app()->instance(CurrentTenant::class,new CurrentTenant($tenant)); return Inertia::render('Inventory/Auth/Login',['portalTenant'=>$tenant->only('id','name','slug')]); }
    public function store(Request $r,string $tenantSlug):RedirectResponse {
        $tenant=Tenant::query()->where('slug',$tenantSlug)->where('status','active')->firstOrFail(); app()->instance(CurrentTenant::class,new CurrentTenant($tenant));
        $d=$r->validate(['email'=>'required|email|max:190','password'=>'required|string|max:200']); $email=Str::lower(trim($d['email'])); $key='inventory-login|'.$tenant->id.'|'.$email.'|'.$r->ip();
        if(RateLimiter::tooManyAttempts($key,5)){ $this->event($tenant->id,null,'rate_limited',$r,['email_hash'=>hash('sha256',$email)]); throw ValidationException::withMessages(['email'=>'Terlalu banyak percobaan login. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.']); }
        $a=InventoryPortalAccount::query()->whereRaw('LOWER(email)=?',[$email])->where('status','active')->first();
        if(!$a || !$a->passwordMatches($d['password'])){ RateLimiter::hit($key,120); $this->event($tenant->id,$a?->id,'failed',$r,['email_hash'=>hash('sha256',$email)]); throw ValidationException::withMessages(['email'=>'Email atau password inventory salah.']); }
        RateLimiter::clear($key); $r->session()->regenerate(); $r->session()->put('inventory_account_id',$a->id); $r->session()->put('inventory_tenant_id',(string)$tenant->id); $a->forceFill(['last_login_at'=>now(),'last_login_ip'=>$r->ip()])->save(); $this->event($tenant->id,$a->id,'login',$r);
        return redirect()->intended(route('inventory.dashboard',['tenantSlug'=>$tenantSlug]));
    }

    public function password(Request $r,string $tenantSlug):RedirectResponse {
        $a=$r->attributes->get('inventory_account');
        $d=$r->validate(['current_password'=>'required|string','password'=>'required|string|min:10|confirmed']);
        if(!$a->passwordMatches($d['current_password'])) throw ValidationException::withMessages(['current_password'=>'Password saat ini salah.']);
        $a->update(['password'=>$d['password'],'must_change_password'=>false]);
        return back()->with('success','Password inventory berhasil diperbarui.');
    }
    public function destroy(Request $r,string $tenantSlug):RedirectResponse { $id=(int)$r->session()->get('inventory_account_id',0); $tenantId=(string)$r->session()->get('inventory_tenant_id',''); $this->event($tenantId,$id?:null,'logout',$r); $r->session()->invalidate(); $r->session()->regenerateToken(); return redirect()->route('inventory.login',['tenantSlug'=>$tenantSlug]); }
    private function event(string $tenantId,?int $accountId,string $event,Request $r,array $meta=[]):void { if($tenantId==='')return; InventoryLoginEvent::query()->create(['tenant_id'=>$tenantId,'inventory_portal_account_id'=>$accountId,'event'=>$event,'ip_address'=>$r->ip(),'user_agent'=>Str::limit((string)$r->userAgent(),500,''),'meta'=>$meta?:null,'created_at'=>now()]); }
}
