<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\WhatsAppSetting;
use App\Support\CurrentTenant;

class PaymentNotificationService
{
    public function __construct(private NotificationService $notifications, private WebhookService $webhooks) {}

    public function invoiceCreated(Invoice $invoice): void
    {
        $invoice->loadMissing('customer');
        $this->safeWebhook('billing.invoice.created', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'customer_id' => $invoice->customer_id,
            'total' => (int) $invoice->total,
            'balance_due' => (int) $invoice->balance_due,
            'due_at' => optional($invoice->due_at)->toDateString(),
        ]);
        if (! $invoice->customer?->phone || ! $this->whatsappEnabled()) return;
        $this->safeQueue('billing.invoice_created', $invoice->customer->phone, [
            'name' => $invoice->customer->name,
            'invoice' => $invoice->invoice_number,
            'amount' => 'Rp'.number_format($invoice->balance_due, 0, ',', '.'),
            'due_date' => optional($invoice->due_at)->format('d-m-Y'),
        ], ['invoice_id' => $invoice->id]);
    }

    public function paymentReceived(Invoice $invoice, Payment $payment): void
    {
        $invoice->loadMissing('customer');
        $this->safeWebhook('billing.payment.posted', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'payment_id' => $payment->id,
            'payment_number' => $payment->payment_number,
            'amount' => (int) $payment->amount,
            'balance_due' => (int) $invoice->balance_due,
            'method' => $payment->method,
        ]);
        if (! $invoice->customer?->phone || ! $this->whatsappEnabled()) return;
        $this->safeQueue('billing.payment_received', $invoice->customer->phone, [
            'name' => $invoice->customer->name,
            'payment' => $payment->payment_number,
            'invoice' => $invoice->invoice_number,
            'amount' => 'Rp'.number_format($payment->amount, 0, ',', '.'),
            'balance' => 'Rp'.number_format($invoice->balance_due, 0, ',', '.'),
        ], ['invoice_id' => $invoice->id, 'payment_id' => $payment->id]);
    }

    public function overdueReminder(Invoice $invoice): void
    {
        $invoice->loadMissing('customer');
        if (! $invoice->customer?->phone || ! $this->whatsappEnabled()) return;
        $this->safeQueue('billing.overdue', $invoice->customer->phone, [
            'name' => $invoice->customer->name,
            'invoice' => $invoice->invoice_number,
            'amount' => 'Rp'.number_format($invoice->balance_due, 0, ',', '.'),
            'due_date' => optional($invoice->due_at)->format('d-m-Y'),
        ], ['invoice_id' => $invoice->id]);
    }

    private function safeQueue(string $code, string $recipient, array $variables, array $payload = []): void
    {
        try { $this->notifications->queueTemplate($code, $recipient, $variables, 'whatsapp', $payload); } catch (\Throwable $e) { report($e); }
    }

    private function safeWebhook(string $event, array $payload): void
    {
        try {
            if (app()->bound(CurrentTenant::class)) $this->webhooks->emit(app(CurrentTenant::class)->tenant, $event, $payload);
        } catch (\Throwable $e) { report($e); }
    }

    private function whatsappEnabled(): bool
    {
        return (bool) WhatsAppSetting::query()->value('enabled');
    }
}
