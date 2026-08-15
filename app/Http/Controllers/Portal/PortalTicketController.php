<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CustomerService;
use App\Models\SupportTicket;
use App\Services\TenantSequenceService;
use App\Support\CurrentTenant;
use App\Support\PortalContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PortalTicketController extends Controller
{
    public function index(string $tenantSlug): Response
    {
        $customerId = PortalContext::customerId();
        return Inertia::render('Portal/Tickets', [
            'portalTenantSlug' => $tenantSlug,
            'tickets' => SupportTicket::query()->where('customer_id',$customerId)
                ->with(['service:id,service_number,pppoe_username','comments'=>fn($q)=>$q->where('is_internal',false)->oldest()])
                ->latest()->get(),
            'services' => CustomerService::query()->where('customer_id',$customerId)->orderBy('service_number')->get(['id','service_number','pppoe_username','status']),
        ]);
    }

    public function store(Request $request, string $tenantSlug, TenantSequenceService $sequences): RedirectResponse
    {
        $data = $request->validate([
            'customer_service_id' => ['nullable','integer'],
            'category' => ['required', Rule::in(['technical','billing','installation','complaint','other'])],
            'priority' => ['required', Rule::in(['low','normal','high','urgent'])],
            'subject' => ['required','string','max:200'],
            'description' => ['required','string','max:5000'],
        ]);
        if (!empty($data['customer_service_id'])) {
            CustomerService::query()->whereKey($data['customer_service_id'])->where('customer_id',PortalContext::customerId())->firstOrFail();
        }
        SupportTicket::create([
            ...$data,
            'ticket_number' => $sequences->next(app(CurrentTenant::class)->id(),'ticket','TKT-',6),
            'customer_id' => PortalContext::customerId(),
            'created_by_portal_account_id' => PortalContext::account()->id,
            'source' => 'portal',
            'status' => 'open',
            'opened_at' => now(),
        ]);
        return back()->with('success','Ticket bantuan berhasil dibuat.');
    }

    public function comment(Request $request, string $tenantSlug, SupportTicket $ticket): RedirectResponse
    {
        abort_unless((int)$ticket->customer_id === PortalContext::customerId(), 404);
        $data=$request->validate(['body'=>'required|string|max:5000']);
        $ticket->comments()->create(['customer_portal_account_id'=>PortalContext::account()->id,'body'=>$data['body'],'is_internal'=>false]);
        if ($ticket->status === 'waiting_customer') $ticket->update(['status'=>'in_progress']);
        return back()->with('success','Balasan berhasil dikirim.');
    }
}
