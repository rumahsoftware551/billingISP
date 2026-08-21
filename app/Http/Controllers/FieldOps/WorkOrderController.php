<?php
namespace App\Http\Controllers\FieldOps;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\SupportTicket;
use App\Models\Technician;
use App\Models\WorkOrder;
use App\Services\TenantSequenceService;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class WorkOrderController extends Controller {
 public function store(Request $r,TenantSequenceService $seq):RedirectResponse { $d=$r->validate(['customer_id'=>'required|integer','customer_service_id'=>'nullable|integer','support_ticket_id'=>'nullable|integer','technician_id'=>'nullable|integer','type'=>['required',Rule::in(['installation','repair','maintenance','survey','relocation','pickup'])],'priority'=>['required',Rule::in(['low','normal','high','urgent'])],'scheduled_at'=>'nullable|date','address'=>'nullable|string|max:2000','latitude'=>'nullable|numeric|between:-90,90','longitude'=>'nullable|numeric|between:-180,180','instructions'=>'nullable|string|max:5000']); $customer=Customer::findOrFail($d['customer_id']); if(!empty($d['customer_service_id'])) CustomerService::whereKey($d['customer_service_id'])->where('customer_id',$customer->id)->firstOrFail(); if(!empty($d['support_ticket_id'])) SupportTicket::whereKey($d['support_ticket_id'])->where('customer_id',$customer->id)->firstOrFail(); if(!empty($d['technician_id'])) Technician::findOrFail($d['technician_id']); WorkOrder::create([...$d,'work_order_number'=>$seq->next(app(CurrentTenant::class)->id(),'work_order','WO-',6),'status'=>'planned','created_by_user_id'=>$r->user()->id]); return back()->with('success','Work order berhasil dibuat.'); }
 public function status(Request $r,WorkOrder $workOrder):RedirectResponse { abort_unless((string)$workOrder->tenant_id===app(CurrentTenant::class)->id(),404); $d=$r->validate(['status'=>['required',Rule::in(['planned','assigned','on_the_way','on_site','completed','cancelled'])],'resolution'=>'nullable|string|max:5000']); $v=['status'=>$d['status'],'resolution'=>$d['resolution']??$workOrder->resolution]; if(in_array($d['status'],['on_site'],true)&&!$workOrder->started_at)$v['started_at']=now(); if($d['status']==='completed')$v['completed_at']=now(); $workOrder->update($v); return back()->with('success','Status work order diperbarui.'); }
}
