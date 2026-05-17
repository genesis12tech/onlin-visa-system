<?php

namespace App\Http\Controllers;

use App\Domain\Payments\Models\Invoice;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceiptDownloadController extends Controller
{
    public function download(Invoice $invoice): StreamedResponse|RedirectResponse
    {
        Gate::authorize('downloadReceipt', $invoice);

        if (! $invoice->pdf_storage_path) {
            return redirect()->back()->with('info', 'Your receipt is not yet available. Please check your email or try again shortly.');
        }

        AuditLogger::log('receipt.downloaded', $invoice, [
            'invoice_number' => $invoice->invoice_number,
        ], auth()->id());

        return Storage::disk('documents')->download(
            $invoice->pdf_storage_path,
            'receipt-'.$invoice->invoice_number.'.pdf',
        );
    }
}
