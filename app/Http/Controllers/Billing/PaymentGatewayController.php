<?php
namespace App\Http\Controllers\Billing;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PaymentGatewayTransaction;
use App\Services\AuditService;
use App\Services\PaymentGatewayNotificationService;
use App\Services\PaymentGatewayService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PaymentGatewayController extends Controller
{
    public function store(Invoice $invoice, PaymentGatewayService $gateway, AuditService $audit): RedirectResponse
    {
        $this->ensureTenantOwnership($invoice);
        $transaction=$gateway->initiate($invoice);
        $audit->record('payment_gateway.transaction_created',PaymentGatewayTransaction::class,$transaction->id,null,['invoice_id'=>$invoice->id,'order_id'=>$transaction->order_id,'provider'=>$transaction->provider,'amount'=>$transaction->amount]);
        return back()->with('success','Link pembayaran berhasil dibuat.');
    }
    public function mock(PaymentGatewayTransaction $transaction)
    {
        $this->ensureTenantOwnership($transaction);
        abort_unless(app()->environment('local')&&$transaction->provider==='mock',404);
        $transaction->load('invoice.customer');
        return Inertia::render('Billing/MockGateway',['transaction'=>$transaction]);
    }
    public function settleMock(PaymentGatewayTransaction $transaction, PaymentGatewayNotificationService $service): RedirectResponse
    {
        $this->ensureTenantOwnership($transaction);
        $service->settleMock($transaction);
        return redirect()->route('billing.invoices.show',$transaction->invoice_id)->with('success','Mock QRIS payment berhasil diselesaikan.');
    }
    public function refresh(PaymentGatewayTransaction $transaction, PaymentGatewayService $gateway): RedirectResponse
    {
        $this->ensureTenantOwnership($transaction);
        if($transaction->provider==='mock') return back()->with('success','Mock transaction status: '.$transaction->status);
        $payload=$gateway->checkStatus($transaction);
        // Status API does not always carry signature_key, so this action only refreshes display metadata.
        $transaction->forceFill(['status_response'=>$payload,'provider_transaction_id'=>$payload['transaction_id']??$transaction->provider_transaction_id,'payment_type'=>$payload['payment_type']??$transaction->payment_type])->save();
        return back()->with('success','Status provider berhasil diambil. Settlement tetap diposting melalui verified notification webhook.');
    }
}
