<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Support\CurrentTenant;

class PortalDocumentService
{
    public function invoicePdf(Invoice $invoice): string
    {
        $invoice->loadMissing(['customer', 'service.plan', 'items']);
        $branding = app(BrandingService::class)->forTenant(app()->bound(CurrentTenant::class) ? app(CurrentTenant::class)->tenant : null);
        $lines = [
            strtoupper((string) ($branding['app_name'] ?: 'Jaringanku')).' - INVOICE',
            'ISP: '.($branding['company_name'] ?: '-'),
            'Invoice: '.$invoice->invoice_number,
            'Customer: '.$invoice->customer?->customer_number.' - '.$invoice->customer?->name,
            'Service: '.($invoice->service?->service_number ?? '-').' / '.($invoice->service?->pppoe_username ?? '-'),
            'Period: '.$invoice->period_start?->format('d-m-Y').' s/d '.$invoice->period_end?->format('d-m-Y'),
            'Issued: '.$invoice->issued_at?->format('d-m-Y'),
            'Due: '.$invoice->due_at?->format('d-m-Y'),
            '',
            'ITEMS',
        ];
        foreach ($invoice->items as $item) {
            $lines[] = $item->description.' | '.number_format((int) $item->amount, 0, ',', '.');
        }
        $lines = [...$lines,
            '',
            'Total: Rp'.number_format((int) $invoice->total, 0, ',', '.'),
            'Paid: Rp'.number_format((int) $invoice->paid_amount, 0, ',', '.'),
            'Balance: Rp'.number_format((int) $invoice->balance_due, 0, ',', '.'),
            'Status: '.strtoupper((string) $invoice->status),
            '',
            (string) ($branding['footer_text'] ?: 'Dokumen dibuat otomatis oleh Jaringanku.'),
        ];
        return $this->pdf($lines);
    }

    public function receiptPdf(Payment $payment): string
    {
        $payment->loadMissing(['customer', 'allocations.invoice']);
        $branding = app(BrandingService::class)->forTenant(app()->bound(CurrentTenant::class) ? app(CurrentTenant::class)->tenant : null);
        $lines = [
            strtoupper((string) ($branding['app_name'] ?: 'Jaringanku')).' - PAYMENT RECEIPT',
            'ISP: '.($branding['company_name'] ?: '-'),
            'Payment: '.$payment->payment_number,
            'Customer: '.$payment->customer?->customer_number.' - '.$payment->customer?->name,
            'Paid at: '.$payment->paid_at?->format('d-m-Y H:i:s'),
            'Method: '.strtoupper((string) $payment->method),
            'Reference: '.($payment->reference ?: '-'),
            'Amount: Rp'.number_format((int) $payment->amount, 0, ',', '.'),
            '',
            'ALLOCATIONS',
        ];
        foreach ($payment->allocations as $allocation) {
            $lines[] = ($allocation->invoice?->invoice_number ?? '-').' | Rp'.number_format((int) $allocation->amount, 0, ',', '.');
        }
        $lines[] = '';
        $lines[] = 'Status: '.strtoupper((string) $payment->status);
        $lines[] = (string) ($branding['footer_text'] ?: 'Dokumen dibuat otomatis oleh Jaringanku.');
        return $this->pdf($lines);
    }

    private function pdf(array $lines): string
    {
        $content = "BT\n/F1 11 Tf\n50 790 Td\n14 TL\n";
        foreach ($lines as $index => $line) {
            if ($index > 0) $content .= "T*\n";
            $content .= '('.$this->escape((string) $line).") Tj\n";
        }
        $content .= "ET";

        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>';
        $objects[] = '<< /Length '.strlen($content).' >>' . "\nstream\n".$content."\nendstream";
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }
        $pdf .= 'trailer << /Size '.(count($objects) + 1).' /Root 1 0 R >>' . "\nstartxref\n".$xref."\n%%EOF";
        return $pdf;
    }

    private function escape(string $value): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $ascii = $ascii === false ? $value : $ascii;
        $ascii = preg_replace('/[^\x20-\x7E]/', '?', $ascii) ?? '';
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii);
    }
}
