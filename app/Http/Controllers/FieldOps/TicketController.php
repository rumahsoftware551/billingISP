<?php
namespace App\Http\Controllers\FieldOps;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\SupportTicket;
use App\Models\Technician;
use App\Services\TenantSequenceService;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class TicketController extends Controller {
 public function store(Request $r,TenantSequenceService $seq):RedirectResponse { $d=$r->validate(['customer_id'=>'required|integer','customer_service_id'=>'nullable|integer','assigned_technician_id'=>'nullable|integer','category'=>['required',Rule::in(['technical','billing','installation','complaint','other'])],'priority'=>['required',Rule::in(['low','normal','high','urgent'])],'subject'=>'required|string|max:200','description'=>'required|string|max:5000']); $customer=Customer::findOrFail($d['customer_id']); if(!empty($d['customer_service_id'])) CustomerService::whereKey($d['customer_service_id'])->where('customer_id',$customer->id)->firstOrFail(); if(!empty($d['assigned_technician_id'])) Technician::findOrFail($d['assigned_technician_id']); SupportTicket::create([...$d,'ticket_number'=>$seq->next(app(CurrentTenant::class)->id(),'ticket','TKT-',6),'source'=>'admin','status'=>'open','opened_at'=>now(),'created_by_user_id'=>$r->user()->id]); return back()->with('success','Ticket berhasil dibuat.'); }
 public function status(Request $r,SupportTicket $ticket):RedirectResponse { abort_unless((string)$ticket->tenant_id===app(CurrentTenant::class)->id(),404); $d=$r->validate(['status'=>['required',Rule::in(['open','in_progress','waiting_customer','resolved','closed','cancelled'])],'assigned_technician_id'=>'nullable|integer']); if(!empty($d['assigned_technician_id'])) Technician::findOrFail($d['assigned_technician_id']); $values=['status'=>$d['status'],'assigned_technician_id'=>$d['assigned_technician_id']??$ticket->assigned_technician_id]; if($d['status']==='in_progress'&&!$ticket->first_response_at)$values['first_response_at']=now(); if($d['status']==='resolved')$values['resolved_at']=now(); if($d['status']==='closed')$values['closed_at']=now(); $ticket->update($values); return back()->with('success','Ticket diperbarui.'); }
 public function comment(Request $r,SupportTicket $ticket):RedirectResponse { abort_unless((string)$ticket->tenant_id===app(CurrentTenant::class)->id(),404); $d=$r->validate(['body'=>'required|string|max:5000','is_internal'=>'nullable|boolean']); $ticket->comments()->create(['user_id'=>$r->user()->id,'body'=>$d['body'],'is_internal'=>(bool)($d['is_internal']??false)]); if(!$ticket->first_response_at)$ticket->update(['first_response_at'=>now()]); return back()->with('success','Komentar ticket ditambahkan.'); }
}
