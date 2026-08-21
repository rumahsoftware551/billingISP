<?php
namespace App\Http\Controllers\Partner;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentNotificationService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
class PartnerBillingController extends Controller {
    public function index(Request $request):Response {
        $a=$request->attributes->get('partner_account');
        abort_unless(in_array($a->role,['owner','admin','collector'],true),403);
        $invoices=Invoice::query()->whereHas('customer',fn($q)=>$q->where('partner_id',$a->partner_id))->with('customer:id,customer_number,name,partner_id')->latest('issued_at')->paginate(20);
        $payments=Payment::query()->whereHas('customer',fn($q)=>$q->where('partner_id',$a->partner_id))->with('customer:id,customer_number,name,partner_id')->latest('paid_at')->limit(20)->get();
        return Inertia::render('Partner/Billing',['invoices'=>$invoices,'payments'=>$payments]);
    }
    public function pay(Request $request,Invoice $invoice,PaymentService $payments,PaymentNotificationService $notifications):RedirectResponse {
        $a=$request->attributes->get('partner_account');
        abort_unless(in_array($a->role,['owner','admin','collector'],true),403);
        abort_unless((int)$invoice->customer()->value('partner_id')===(int)$a->partner_id,404);
        $data=$request->validate(['amount'=>['required','integer','min:1'],'method'=>['required',Rule::in(['cash','transfer','qris','other'])],'reference'=>['nullable','string','max:160'],'notes'=>['nullable','string','max:1000'],'idempotency_key'=>['required','uuid']]);
        $payment=$payments->postToInvoice($invoice,(int)$data['amount'],$data['method'],$data['reference']??null,now(),$data['notes']??'Pembayaran diterima Portal Mitra',null,$a->partner_id,$a->id,$data['idempotency_key']);
        if(!$payment->getAttribute('idempotent_replay')) $notifications->paymentReceived($invoice->fresh(['customer']),$payment);
        return back()->with('success',$payment->getAttribute('idempotent_replay')?'Permintaan ini sudah diproses sebagai pembayaran '.$payment->payment_number.'.':'Pembayaran '.$payment->payment_number.' berhasil diposting.');
    }
}
