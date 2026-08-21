<?php
namespace App\Http\Controllers\Inventory;
use App\Http\Controllers\Controller;
use App\Models\InventoryLocation;
use App\Models\InventoryPortalAccount;
use App\Models\Technician;
use App\Services\TenantSequenceService;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
class InventoryAdminController extends Controller {
    public function index():Response { return Inertia::render('InventoryAdmin/Index',['accounts'=>InventoryPortalAccount::query()->with(['location:id,code,name','technician:id,code,name'])->orderBy('name')->get(),'locations'=>InventoryLocation::query()->with('technician:id,code,name')->orderBy('name')->get(),'technicians'=>Technician::query()->where('status','active')->orderBy('name')->get(['id','code','name'])]); }
    public function location(Request $r,TenantSequenceService $seq):RedirectResponse { $d=$r->validate(['code'=>'nullable|string|max:60','name'=>'required|string|max:160','location_type'=>'required|in:warehouse,technician,transit,repair','technician_id'=>'nullable|integer','address'=>'nullable|string|max:2000']); if($d['location_type']==='technician' && empty($d['technician_id'])) abort(422,'Lokasi technician wajib memilih teknisi.'); if(!empty($d['technician_id']))Technician::findOrFail($d['technician_id']); $code=trim((string)($d['code']??'')); if($code==='')$code=$seq->next(app(CurrentTenant::class)->id(),'inventory_location','LOC-',4); InventoryLocation::create([...$d,'code'=>$code,'active'=>true]); return back()->with('success','Lokasi inventory dibuat.'); }
    public function account(Request $r):RedirectResponse { $d=$r->validate(['name'=>'required|string|max:160','email'=>'required|email|max:190','role'=>'required|in:warehouse_manager,warehouse_staff,technician,auditor','inventory_location_id'=>'nullable|integer','technician_id'=>'nullable|integer']); if(!empty($d['inventory_location_id']))$loc=InventoryLocation::findOrFail($d['inventory_location_id']); else $loc=null; if(!empty($d['technician_id']))$tech=Technician::findOrFail($d['technician_id']); else $tech=null; if($d['role']==='technician'){ abort_unless($loc && $tech,422,'Akun technician wajib memiliki lokasi stok dan teknisi.'); abort_unless($loc->location_type==='technician' && (int)$loc->technician_id===(int)$tech->id,422,'Lokasi stok technician harus sesuai dengan teknisi yang dipilih.'); } $d['email']=Str::lower(trim($d['email'])); abort_if(InventoryPortalAccount::query()->whereRaw('LOWER(email)=?',[$d['email']])->exists(),422,'Email inventory sudah digunakan.'); $password=Str::password(14); InventoryPortalAccount::create([...$d,'password'=>$password,'status'=>'active','must_change_password'=>true]); return back()->with('success','Akun inventory dibuat. Password sementara: '.$password); }
    public function status(Request $r,InventoryPortalAccount $account):RedirectResponse { abort_unless((string)$account->tenant_id===app(CurrentTenant::class)->id(),404); $d=$r->validate(['status'=>'required|in:active,disabled']); $account->update($d); return back()->with('success','Status akun inventory diperbarui.'); }
}
