<?php
namespace App\Http\Controllers\FieldOps;
use App\Http\Controllers\Controller;
use App\Models\CustomerService;
use App\Models\InstallationJob;
use App\Models\Technician;
use App\Models\WorkOrder;
use App\Services\RadiusProjectionService;
use App\Services\PartnerCommissionService;
use App\Services\TenantSequenceService;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class InstallationController extends Controller {
 public function store(Request $r,TenantSequenceService $seq):RedirectResponse { $d=$r->validate(['customer_service_id'=>'required|integer','technician_id'=>'nullable|integer','scheduled_at'=>'nullable|date','installation_notes'=>'nullable|string|max:5000']); $service=CustomerService::findOrFail($d['customer_service_id']); if(!empty($d['technician_id']))Technician::findOrFail($d['technician_id']); $wo=WorkOrder::create(['work_order_number'=>$seq->next(app(CurrentTenant::class)->id(),'work_order','WO-',6),'customer_id'=>$service->customer_id,'customer_service_id'=>$service->id,'technician_id'=>$d['technician_id']??null,'created_by_user_id'=>$r->user()->id,'type'=>'installation','priority'=>'normal','status'=>'planned','scheduled_at'=>$d['scheduled_at']??null,'instructions'=>$d['installation_notes']??null]); InstallationJob::create(['installation_number'=>$seq->next(app(CurrentTenant::class)->id(),'installation','INST-',6),'customer_service_id'=>$service->id,'technician_id'=>$d['technician_id']??null,'work_order_id'=>$wo->id,'status'=>'planned','scheduled_at'=>$d['scheduled_at']??null,'installation_notes'=>$d['installation_notes']??null]); return back()->with('success','Job instalasi berhasil dibuat.'); }
 public function status(Request $r,InstallationJob $installation,RadiusProjectionService $radius,PartnerCommissionService $commissions):RedirectResponse { abort_unless((string)$installation->tenant_id===app(CurrentTenant::class)->id(),404); $d=$r->validate(['status'=>['required',Rule::in(['planned','scheduled','on_the_way','on_site','activated','completed','cancelled'])],'installation_notes'=>'nullable|string|max:5000']); DB::transaction(function()use($installation,$d,$radius){$v=['status'=>$d['status'],'installation_notes'=>$d['installation_notes']??$installation->installation_notes]; if($d['status']==='on_site'&&!$installation->arrived_at)$v['arrived_at']=now(); if(in_array($d['status'],['activated','completed'],true)&&!$installation->activated_at)$v['activated_at']=now(); if($d['status']==='completed')$v['completed_at']=now(); $installation->update($v); if(in_array($d['status'],['activated','completed'],true)){ $service=$installation->service; $from=$service->status; $service->update(['status'=>'active','installed_at'=>$service->installed_at??now()]); if($from!=='active')$service->statusHistories()->create(['from_status'=>$from,'to_status'=>'active','reason'=>'Aktivasi melalui installation workflow']); $radius->syncService($service->fresh()); } if($installation->workOrder){$installation->workOrder->update(['status'=>$d['status']==='completed'?'completed':($d['status']==='cancelled'?'cancelled':'on_site'),'completed_at'=>$d['status']==='completed'?now():$installation->workOrder->completed_at]);}}); if(in_array($d['status'],['activated','completed'],true)){try{$commissions->accrueActivationForService($installation->service->fresh());}catch(\Throwable $e){report($e);}} return back()->with('success','Status instalasi diperbarui.'); }
}
