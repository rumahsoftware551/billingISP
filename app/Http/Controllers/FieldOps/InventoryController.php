<?php
namespace App\Http\Controllers\FieldOps;
use App\Http\Controllers\Controller;
use App\Models\CustomerService;
use App\Models\InventoryItem;
use App\Models\Technician;
use App\Services\TenantSequenceService;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class InventoryController extends Controller {
 public function store(Request $r,TenantSequenceService $seq):RedirectResponse { $d=$r->validate(['category'=>'required|string|max:50','brand'=>'nullable|string|max:100','model'=>'nullable|string|max:120','serial_number'=>'nullable|string|max:160','mac_address'=>'nullable|string|max:40','purchase_date'=>'nullable|date','warranty_until'=>'nullable|date','notes'=>'nullable|string|max:2000']); InventoryItem::create([...$d,'asset_code'=>$seq->next(app(CurrentTenant::class)->id(),'inventory','AST-',6),'status'=>'available']); return back()->with('success','Asset inventory ditambahkan.'); }
 public function assign(Request $r,InventoryItem $item):RedirectResponse { abort_unless((string)$item->tenant_id===app(CurrentTenant::class)->id(),404); if($item->inventory_sku_id){ return back()->with('error','Asset yang sudah dikelola Phase 14 harus dipindahkan melalui Portal Inventory agar ledger stok tetap konsisten.'); } $d=$r->validate(['assigned_customer_service_id'=>'nullable|integer','assigned_technician_id'=>'nullable|integer','status'=>['required',Rule::in(['available','assigned_customer','assigned_technician','repair','retired','lost'])],'notes'=>'nullable|string|max:2000']); if(!empty($d['assigned_customer_service_id']))CustomerService::findOrFail($d['assigned_customer_service_id']); if(!empty($d['assigned_technician_id']))Technician::findOrFail($d['assigned_technician_id']); DB::transaction(function()use($r,$item,$d){$from=$item->status; $item->update($d); $item->movements()->create(['movement_type'=>'assignment','from_status'=>$from,'to_status'=>$d['status'],'customer_service_id'=>$d['assigned_customer_service_id']??null,'technician_id'=>$d['assigned_technician_id']??null,'actor_user_id'=>$r->user()->id,'notes'=>$d['notes']??null]);}); return back()->with('success','Assignment inventory diperbarui.'); }
}
