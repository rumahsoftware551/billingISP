<?php
namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformEvent;
use App\Models\PlatformPlan;
use App\Models\ReleaseRecord;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\SaasPlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PlatformController extends Controller
{
    public function index(SaasPlanService $saas)
    {
        $tenants = Tenant::query()->with(['subscription.plan'])->orderBy('name')->get()->map(function(Tenant $tenant) use ($saas) {
            return [
                ...$tenant->only('id','name','slug','status','timezone','currency','created_at'),
                'subscription' => $tenant->subscription ? [
                    ...$tenant->subscription->only('id','status','trial_ends_at','current_period_end','grace_ends_at'),
                    'plan' => $tenant->subscription->plan?->only('id','code','name','monthly_price'),
                ] : null,
                'usage' => $saas->usage($tenant),
            ];
        });

        return Inertia::render('Platform/Index', [
            'stats' => [
                'tenants' => Tenant::count(),
                'active_tenants' => Tenant::where('status','active')->count(),
                'active_subscriptions' => TenantSubscription::whereIn('status',['trialing','active'])->count(),
                'customers' => DB::table('customers')->whereNull('deleted_at')->count(),
                'services' => DB::table('customer_services')->whereNull('deleted_at')->count(),
            ],
            'plans' => PlatformPlan::query()->orderBy('monthly_price')->get(),
            'tenants' => $tenants,
            'events' => PlatformEvent::query()->with(['tenant:id,name','user:id,name,email'])->latest('id')->limit(30)->get(),
            'releases' => ReleaseRecord::query()->with('user:id,name,email')->latest('id')->limit(20)->get(),
            'release' => ['version'=>config('jaringanku.version'),'channel'=>config('jaringanku.release_channel')],
        ]);
    }

    public function storeTenant(Request $request)
    {
        $data = $request->validate([
            'name'=>['required','string','max:150'],
            'slug'=>['required','alpha_dash','max:100','unique:tenants,slug'],
            'owner_name'=>['required','string','max:150'],
            'owner_email'=>['required','email','max:255','unique:users,email'],
            'owner_password'=>['required','string','min:12','max:128'],
            'platform_plan_id'=>['required', Rule::exists('platform_plans','id')->where('active', true)],
        ]);

        $tenant = DB::transaction(function() use ($data, $request) {
            $tenant = Tenant::create(['name'=>$data['name'],'slug'=>$data['slug'],'status'=>'active','timezone'=>'Asia/Jakarta','currency'=>'IDR']);
            $roleId = DB::table('roles')->insertGetId(['tenant_id'=>$tenant->id,'name'=>'Owner','slug'=>'owner','created_at'=>now(),'updated_at'=>now()]);
            $user = User::firstOrCreate(['email'=>$data['owner_email']], ['name'=>$data['owner_name'],'password'=>Hash::make($data['owner_password']),'is_platform_admin'=>false]);
            DB::table('tenant_memberships')->updateOrInsert(['tenant_id'=>$tenant->id,'user_id'=>$user->id],['role_id'=>$roleId,'is_default'=>true,'created_at'=>now(),'updated_at'=>now()]);
            TenantSubscription::create(['tenant_id'=>$tenant->id,'platform_plan_id'=>$data['platform_plan_id'],'status'=>'trialing','trial_ends_at'=>now()->addDays(14),'current_period_start'=>now(),'current_period_end'=>now()->addMonth()]);
            PlatformEvent::create(['tenant_id'=>$tenant->id,'user_id'=>$request->user()->id,'event'=>'tenant.created','payload'=>['slug'=>$tenant->slug,'owner_email'=>$data['owner_email']]]);
            return $tenant;
        });

        return back()->with('success', "Tenant {$tenant->name} berhasil dibuat dengan trial 14 hari.");
    }

    public function updateSubscription(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'platform_plan_id'=>['required', Rule::exists('platform_plans','id')->where('active', true)],
            'status'=>['required', Rule::in(['trialing','active','past_due','suspended','canceled'])],
            'period_days'=>['nullable','integer','min:1','max:3660'],
        ]);
        $days = (int)($data['period_days'] ?? 30);
        $subscription = TenantSubscription::updateOrCreate(['tenant_id'=>$tenant->id],[
            'platform_plan_id'=>$data['platform_plan_id'],
            'status'=>$data['status'],
            'trial_ends_at'=>$data['status']==='trialing' ? now()->addDays($days) : null,
            'current_period_start'=>now(),
            'current_period_end'=>in_array($data['status'], ['active','past_due'], true) ? now()->addDays($days) : null,
            'grace_ends_at'=>$data['status']==='past_due' ? now()->addDays(7) : null,
        ]);
        PlatformEvent::create(['tenant_id'=>$tenant->id,'user_id'=>$request->user()->id,'event'=>'subscription.updated','payload'=>['plan_id'=>$subscription->platform_plan_id,'status'=>$subscription->status]]);
        return back()->with('success','Subscription tenant berhasil diperbarui.');
    }

    public function updateTenantStatus(Request $request, Tenant $tenant)
    {
        $data = $request->validate(['status'=>['required',Rule::in(['active','suspended','closed'])]]);
        $tenant->update(['status'=>$data['status']]);
        PlatformEvent::create(['tenant_id'=>$tenant->id,'user_id'=>$request->user()->id,'event'=>'tenant.status_changed','payload'=>['status'=>$tenant->status]]);
        return back()->with('success','Status tenant berhasil diperbarui.');
    }
}
