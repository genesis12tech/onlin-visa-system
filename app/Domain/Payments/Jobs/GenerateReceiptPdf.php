<?php

namespace App\Domain\Payments\Jobs;

use App\Domain\Payments\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateReceiptPdf implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $invoiceUlid) {}

    public function handle(): void
    {
        $invoice = Invoice::with(['payment.items', 'payment.visaApplication'])->findOrFail($this->invoiceUlid);
        $payment = $invoice->payment;

        $pdf = Pdf::loadView('pdfs.receipt', compact('invoice', 'payment'));

        $storagePath = 'receipts/'.Str::ulid().'.pdf';

        Storage::disk('documents')->put($storagePath, $pdf->output());

        $invoice->update(['pdf_storage_path' => $storagePath]);
    }
}
