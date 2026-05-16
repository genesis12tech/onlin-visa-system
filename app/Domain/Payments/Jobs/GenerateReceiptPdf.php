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

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [10, 30, 60];

    public function __construct(public readonly string $invoiceUlid) {}

    public function handle(): void
    {
        $invoice = Invoice::with(['payment.items', 'payment.visaApplication'])->findOrFail($this->invoiceUlid);
        $payment = $invoice->payment;

        if ($payment === null) {
            $this->fail(new \RuntimeException("Invoice {$this->invoiceUlid} has no associated payment."));

            return;
        }

        $pdf = Pdf::loadView('pdfs.receipt', compact('invoice', 'payment'));

        $storagePath = 'receipts/'.Str::ulid().'.pdf';

        $written = Storage::disk('documents')->put($storagePath, $pdf->output());

        if (! $written) {
            $this->fail(new \RuntimeException("Failed to write PDF to {$storagePath}"));

            return;
        }

        $invoice->update(['pdf_storage_path' => $storagePath]);
    }
}
