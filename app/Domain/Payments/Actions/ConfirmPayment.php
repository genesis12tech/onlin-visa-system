<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Jobs\GenerateReceiptPdf;
use App\Domain\Payments\Models\Invoice;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentSucceededNotification;
use Illuminate\Support\Facades\DB;

class ConfirmPayment
{
    public function execute(Payment $payment, ?User $actor): void
    {
        if ($payment->status === PaymentStatus::Succeeded) {
            return;
        }

        $invoiceUlid = null;

        DB::transaction(function () use ($payment, $actor, &$invoiceUlid): void {
            $payment->update([
                'status' => PaymentStatus::Succeeded,
                'succeeded_at' => now(),
            ]);

            $application = $payment->visaApplication;

            if (! $application) {
                throw new \RuntimeException("Payment {$payment->ulid} has no associated visa application.");
            }

            $application->update(['status' => ApplicationStatus::PaymentCompleted]);

            ApplicationStatusHistory::create([
                'visa_application_id' => $application->ulid,
                'from_status' => ApplicationStatus::PaymentPending->value,
                'to_status' => ApplicationStatus::PaymentCompleted->value,
                'actor_id' => $actor?->id,
                'created_at' => now(),
            ]);

            $invoice = Invoice::create([
                'payment_id' => $payment->ulid,
                'invoice_number' => $this->generateInvoiceNumber(),
                'issued_at' => now(),
            ]);

            $invoiceUlid = $invoice->ulid;

            GenerateReceiptPdf::dispatch($invoiceUlid)->onQueue('pdfs');
        });

        $payment->load(['visaApplication.applicantProfile.user', 'invoice']);

        $applicantUser = $payment->visaApplication?->applicantProfile?->user;

        if ($applicantUser && $payment->invoice) {
            $applicantUser->notify(new PaymentSucceededNotification($payment, $payment->invoice));
        }
    }

    private function generateInvoiceNumber(): string
    {
        $year = now()->format('Y');
        $maxAttempts = 10;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $count = Invoice::whereYear('issued_at', $year)->count() + $attempt;
            $candidate = 'INV-'.$year.'-'.str_pad((string) $count, 6, '0', STR_PAD_LEFT);

            if (! Invoice::where('invoice_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'INV-'.$year.'-'.now()->format('Hisu');
    }
}
