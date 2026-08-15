<?php

namespace App\Http\Controllers\Commercial;

use App\Http\Controllers\Controller;
use App\Models\CustomPaymentMethod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TenantBranding;
use App\Models\User;
use App\Services\BrandingService;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(BrandingService $branding): Response
    {
        $tenant = app(CurrentTenant::class)->tenant;
        $users = DB::table('tenant_memberships')
            ->join('users','users.id','=','tenant_memberships.user_id')
            ->leftJoin('roles','roles.id','=','tenant_memberships.role_id')
            ->where('tenant_memberships.tenant_id',$tenant->id)
            ->orderBy('users.name')
            ->get([
                'users.id','users.name','users.email','users.last_login_at','users.last_login_ip',
                'tenant_memberships.role_id','tenant_memberships.status as membership_status','tenant_memberships.is_default',
                'roles.name as role_name','roles.slug as role_slug',
            ]);

        $roles = Role::query()->where('tenant_id',$tenant->id)->with('permissions:id,name,slug')->orderBy('name')->get();
        $methods = CustomPaymentMethod::query()->orderBy('sort_order')->orderBy('name')->get()->map(function($m){
            $m->setAttribute('qr_image_url', $m->qr_image_path ? Storage::disk('public')->url($m->qr_image_path) : null);
            return $m;
        });

        return Inertia::render('Settings/Index', [
            'brandingSettings' => TenantBranding::query()->where('tenant_id',$tenant->id)->first(),
            'brandingPreview' => $branding->forTenant($tenant),
            'paymentMethods' => $methods,
            'users' => $users,
            'roles' => $roles,
            'permissions' => Permission::query()->orderBy('slug')->get(['id','name','slug']),
        ]);
    }

    public function updateBranding(Request $request): RedirectResponse
    {
        $tenant = app(CurrentTenant::class)->tenant;
        $data = $request->validate([
            'app_name'=>['required','string','max:80'],
            'company_name'=>['nullable','string','max:160'],
            'portal_title'=>['nullable','string','max:160'],
            'primary_color'=>['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color'=>['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'support_phone'=>['nullable','string','max:60'],
            'support_email'=>['nullable','email','max:190'],
            'address'=>['nullable','string','max:2000'],
            'footer_text'=>['nullable','string','max:255'],
            'show_powered_by'=>['nullable','boolean'],
            'logo'=>['nullable','image','mimes:png,jpg,jpeg,webp','max:4096'],
            'login_logo'=>['nullable','image','mimes:png,jpg,jpeg,webp','max:4096'],
            'favicon'=>['nullable','image','mimes:png,jpg,jpeg,webp','max:2048'],
            'invoice_logo'=>['nullable','image','mimes:png,jpg,jpeg,webp','max:4096'],
        ]);
        $row = TenantBranding::query()->firstOrNew(['tenant_id'=>$tenant->id]);
        foreach(['logo','login_logo','favicon','invoice_logo'] as $field){
            if($request->hasFile($field)){
                $column=$field.'_path';
                if($row->{$column}) Storage::disk('public')->delete($row->{$column});
                $row->{$column}=$request->file($field)->store('tenant-branding/'.$tenant->slug,'public');
            }
            unset($data[$field]);
        }
        $row->fill([...$data,'show_powered_by'=>$request->boolean('show_powered_by')]);
        $row->tenant_id=$tenant->id;
        $row->save();
        return back()->with('success','Branding aplikasi berhasil diperbarui.');
    }

    public function storePaymentMethod(Request $request): RedirectResponse
    {
        $tenant=app(CurrentTenant::class)->tenant;
        $data=$this->paymentData($request);
        $method=new CustomPaymentMethod();
        $method->fill($data);
        $method->code=$data['code'];
        if($request->hasFile('qr_image')) $method->qr_image_path=$request->file('qr_image')->store('payment-methods/'.$tenant->slug,'public');
        $method->save();
        return back()->with('success','Metode pembayaran custom berhasil ditambahkan.');
    }

    public function updatePaymentMethod(Request $request, CustomPaymentMethod $method): RedirectResponse
    {
        $this->ensureTenantOwnership($method);
        $data=$this->paymentData($request,$method->id);
        if($request->hasFile('qr_image')){
            if($method->qr_image_path) Storage::disk('public')->delete($method->qr_image_path);
            $method->qr_image_path=$request->file('qr_image')->store('payment-methods/'.app(CurrentTenant::class)->tenant->slug,'public');
        }
        $method->fill($data)->save();
        return back()->with('success','Metode pembayaran berhasil diperbarui.');
    }

    public function destroyPaymentMethod(CustomPaymentMethod $method): RedirectResponse
    {
        $this->ensureTenantOwnership($method);
        if($method->proofs()->exists()) throw ValidationException::withMessages(['payment_method'=>'Metode sudah memiliki histori bukti pembayaran dan tidak boleh dihapus. Nonaktifkan metode agar audit tetap utuh.']);
        if($method->qr_image_path) Storage::disk('public')->delete($method->qr_image_path);
        $method->delete();
        return back()->with('success','Metode pembayaran dihapus.');
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $tenantId=app(CurrentTenant::class)->id();
        $data=$request->validate(['name'=>'required|string|max:100','slug'=>'nullable|string|max:100','description'=>'nullable|string|max:255','permissions'=>'array','permissions.*'=>'integer|exists:permissions,id']);
        $slug=Str::slug(($data['slug'] ?? null) ?: $data['name']);
        if(Role::query()->where('tenant_id',$tenantId)->where('slug',$slug)->exists()) throw ValidationException::withMessages(['slug'=>'Slug role sudah digunakan pada ISP ini.']);
        if(in_array($slug,['owner','admin'],true)) throw ValidationException::withMessages(['slug'=>'Slug owner/admin dicadangkan oleh sistem.']);
        $role=Role::create(['tenant_id'=>$tenantId,'name'=>$data['name'],'slug'=>$slug,'description'=>$data['description']??null]);
        $role->permissions()->sync($data['permissions']??[]);
        return back()->with('success','Role '.$role->name.' berhasil dibuat.');
    }

    public function updateRole(Request $request, Role $role): RedirectResponse
    {
        abort_unless((string)$role->tenant_id===app(CurrentTenant::class)->id(),404);
        if(in_array($role->slug,['owner','admin'],true)) throw ValidationException::withMessages(['role'=>'Role owner/admin sistem tidak dapat diubah dari matriks custom.']);
        $data=$request->validate(['name'=>'required|string|max:100','description'=>'nullable|string|max:255','permissions'=>'array','permissions.*'=>'integer|exists:permissions,id']);
        $role->update(['name'=>$data['name'],'description'=>$data['description']??null]);
        $role->permissions()->sync($data['permissions']??[]);
        return back()->with('success','Role dan permission diperbarui.');
    }

    public function destroyRole(Role $role): RedirectResponse
    {
        abort_unless((string)$role->tenant_id===app(CurrentTenant::class)->id(),404);
        if(in_array($role->slug,['owner','admin'],true)) throw ValidationException::withMessages(['role'=>'Role owner/admin tidak dapat dihapus.']);
        $used=DB::table('tenant_memberships')->where('tenant_id',app(CurrentTenant::class)->id())->where('role_id',$role->id)->exists();
        if($used) throw ValidationException::withMessages(['role'=>'Role masih digunakan user. Pindahkan user ke role lain terlebih dahulu.']);
        $role->delete();
        return back()->with('success','Role dihapus.');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $tenantId=app(CurrentTenant::class)->id();
        $data=$request->validate([
            'name'=>'required|string|max:160','email'=>'required|email|max:190','role_id'=>['required',Rule::exists('roles','id')->where(fn($q)=>$q->where('tenant_id',$tenantId))],
            'password'=>'nullable|string|min:10|max:190',
        ]);
        $email=Str::lower(trim($data['email']));
        $user=User::query()->whereRaw('LOWER(email)=?',[$email])->first();
        if($user && DB::table('tenant_memberships')->where('tenant_id',$tenantId)->where('user_id',$user->id)->exists()) throw ValidationException::withMessages(['email'=>'User sudah terdaftar pada ISP ini.']);
        $generated=null;
        if(!$user){
            $generated=($data['password'] ?? null) ?: Str::password(14);
            $user=User::create(['name'=>$data['name'],'email'=>$email,'password'=>Hash::make($generated),'status'=>'active','is_platform_admin'=>false]);
        }
        DB::table('tenant_memberships')->insert(['tenant_id'=>$tenantId,'user_id'=>$user->id,'role_id'=>$data['role_id'],'is_default'=>false,'status'=>'active','created_at'=>now(),'updated_at'=>now()]);
        $message='User '.$user->email.' berhasil ditambahkan.';
        if($generated) $message.=' Password sementara: '.$generated;
        return back()->with('success',$message)->with('generated_user_password',$generated);
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $tenantId=app(CurrentTenant::class)->id();
        $membership=DB::table('tenant_memberships')->where('tenant_id',$tenantId)->where('user_id',$user->id)->first();
        abort_unless($membership,404);
        $data=$request->validate(['role_id'=>['required',Rule::exists('roles','id')->where(fn($q)=>$q->where('tenant_id',$tenantId))],'status'=>'required|in:active,disabled']);
        if((int)$user->id===(int)auth()->id() && $data['status']==='disabled') throw ValidationException::withMessages(['status'=>'Anda tidak dapat menonaktifkan akun sendiri.']);
        $currentRoleSlug=DB::table('roles')->where('id',$membership->role_id)->value('slug');
        $nextRoleSlug=DB::table('roles')->where('id',$data['role_id'])->value('slug');
        if(in_array($currentRoleSlug,['owner','admin'],true) && ($data['status']!=='active' || !in_array($nextRoleSlug,['owner','admin'],true))) $this->ensureAnotherPrivilegedUser($tenantId,$user->id);
        DB::table('tenant_memberships')->where('id',$membership->id)->update(['role_id'=>$data['role_id'],'status'=>$data['status'],'updated_at'=>now()]);
        return back()->with('success','Role/status user diperbarui.');
    }

    public function resetUserPassword(User $user): RedirectResponse
    {
        $tenantId=app(CurrentTenant::class)->id();
        abort_unless(DB::table('tenant_memberships')->where('tenant_id',$tenantId)->where('user_id',$user->id)->exists(),404);
        if(!auth()->user()?->is_platform_admin && DB::table('tenant_memberships')->where('user_id',$user->id)->count()>1) throw ValidationException::withMessages(['user'=>'Akun ini digunakan pada lebih dari satu tenant. Demi keamanan, reset password dilakukan oleh user sendiri atau Platform Admin.']);
        $password=Str::password(14);
        $user->forceFill(['password'=>Hash::make($password)])->save();
        return back()->with('success','Password '.$user->email.' direset: '.$password)->with('generated_user_password',$password);
    }

    public function removeUser(User $user): RedirectResponse
    {
        $tenantId=app(CurrentTenant::class)->id();
        if((int)$user->id===(int)auth()->id()) throw ValidationException::withMessages(['user'=>'Anda tidak dapat menghapus membership akun sendiri.']);
        $membership=DB::table('tenant_memberships')->where('tenant_id',$tenantId)->where('user_id',$user->id)->first();
        abort_unless($membership,404);
        $roleSlug=DB::table('roles')->where('id',$membership->role_id)->value('slug');
        if(in_array($roleSlug,['owner','admin'],true) && $membership->status==='active') $this->ensureAnotherPrivilegedUser($tenantId,$user->id);
        DB::table('tenant_memberships')->where('id',$membership->id)->delete();
        return back()->with('success','User dilepas dari ISP ini.');
    }

    private function ensureAnotherPrivilegedUser(string $tenantId, int $excludedUserId): void
    {
        $exists=DB::table('tenant_memberships')
            ->join('roles','roles.id','=','tenant_memberships.role_id')
            ->where('tenant_memberships.tenant_id',$tenantId)
            ->where('tenant_memberships.status','active')
            ->where('tenant_memberships.user_id','<>',$excludedUserId)
            ->whereIn('roles.slug',['owner','admin'])
            ->exists();
        if(!$exists) throw ValidationException::withMessages(['user'=>'Tidak dapat menghapus/demosi administrator terakhir. Tambahkan owner/admin aktif lain terlebih dahulu.']);
    }

    private function paymentData(Request $request, ?int $ignoreId=null): array
    {
        $tenantId=app(CurrentTenant::class)->id();
        $codeRule=Rule::unique('custom_payment_methods','code')->where(fn($q)=>$q->where('tenant_id',$tenantId));
        if($ignoreId) $codeRule=$codeRule->ignore($ignoreId);
        $data=$request->validate([
            'name'=>'required|string|max:160','code'=>['nullable','string','max:80',$codeRule],
            'type'=>'required|in:qris,bank_transfer,e_wallet,cash,custom','bank_name'=>'nullable|string|max:120','account_name'=>'nullable|string|max:160','account_number'=>'nullable|string|max:160',
            'instructions'=>'nullable|string|max:3000','admin_fee_type'=>'required|in:none,fixed,percent','admin_fee_value'=>'required|integer|min:0|max:100000000',
            'minimum_amount'=>'required|integer|min:0','maximum_amount'=>'nullable|integer|min:1','customer_visible'=>'nullable|boolean','partner_visible'=>'nullable|boolean','active'=>'nullable|boolean','sort_order'=>'required|integer|min:0|max:9999',
            'qr_image'=>'nullable|image|mimes:png,jpg,jpeg,webp|max:4096',
        ]);
        unset($data['qr_image']);
        $rawCode=$data['code'] ?? null;
        $data['code']=$rawCode ? Str::slug($rawCode) : Str::slug($data['name']);
        if($data['code']==='') throw ValidationException::withMessages(['code'=>'Kode metode pembayaran tidak valid.']);
        $duplicate=CustomPaymentMethod::query()->withoutGlobalScopes()->where('tenant_id',$tenantId)->where('code',$data['code'])->when($ignoreId,fn($q)=>$q->where('id','<>',$ignoreId))->exists();
        if($duplicate) throw ValidationException::withMessages(['code'=>'Kode metode pembayaran sudah digunakan pada ISP ini.']);
        $data['customer_visible']=$request->boolean('customer_visible');
        $data['partner_visible']=$request->boolean('partner_visible');
        $data['active']=$request->boolean('active');
        if(($data['maximum_amount'] ?? null)!==null && (int)$data['maximum_amount'] < (int)$data['minimum_amount']) throw ValidationException::withMessages(['maximum_amount'=>'Maksimum pembayaran tidak boleh lebih kecil dari minimum pembayaran.']);
        if($data['admin_fee_type']==='percent' && $data['admin_fee_value']>100) throw ValidationException::withMessages(['admin_fee_value'=>'Biaya admin persen maksimal 100%.']);
        return $data;
    }
}
