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
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConfirmPayment
{
    public function execute(Payment $payment, ?User $actor): void
    {
        // Fast path: in-memory model already reflects Succeeded state.
        if ($payment->status === PaymentStatus::Succeeded) {
            return;
        }

        $invoiceUlid = null;

        DB::transaction(function () use ($payment, $actor, &$invoiceUlid): void {
            // Re-fetch with a row lock so concurrent calls (e.g. webhook retry + manual
            // mark-as-paid) cannot both pass the status check simultaneously.
            $locked = Payment::where('ulid', $payment->ulid)->lockForUpdate()->first();

            if (! $locked || $locked->status === PaymentStatus::Succeeded) {
                return;
            }

            $locked->update([
                'status' => PaymentStatus::Succeeded,
                'succeeded_at' => now(),
            ]);

            $application = $locked->visaApplication;

            if (! $application) {
                throw new \RuntimeException("Payment {$locked->ulid} has no associated visa application.");
            }

            $application->update(['status' => ApplicationStatus::PaymentCompleted]);

            ApplicationStatusHistory::create([
                'visa_application_id' => $application->ulid,
                'from_status' => ApplicationStatus::PaymentPending->value,
                'to_status' => ApplicationStatus::PaymentCompleted->value,
                'actor_id' => $actor?->id,
                'created_at' => now(),
            ]);

            $invoice = $this->createInvoice($locked);
            $invoiceUlid = $invoice->ulid;

            GenerateReceiptPdf::dispatch($invoiceUlid)->onQueue('pdfs');
        });

        if (! $invoiceUlid) {
            return;
        }

        $payment->load(['visaApplication.applicantProfile.user', 'invoice']);

        $applicantUser = $payment->visaApplication?->applicantProfile?->user;

        if ($applicantUser && $payment->invoice) {
            $applicantUser->notify(new PaymentSucceededNotification($payment, $payment->invoice));
        }
    }

    private function createInvoice(Payment $payment): Invoice
    {
        $year = now()->format('Y');

        // Try sequential numbers first. On a unique constraint violation (concurrent
        // confirmation), catch and try the next slot — the DB is the arbiter, not a
        // pre-check. After 10 misses fall back to a ULID suffix that cannot collide.
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $count = Invoice::whereYear('issued_at', $year)->count() + $attempt;
            $invoiceNumber = 'INV-'.$year.'-'.str_pad((string) $count, 6, '0', STR_PAD_LEFT);

            try {
                return Invoice::create([
                    'payment_id' => $payment->ulid,
                    'invoice_number' => $invoiceNumber,
                    'issued_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        return Invoice::create([
            'payment_id' => $payment->ulid,
            'invoice_number' => 'INV-'.$year.'-'.Str::upper(Str::ulid()),
            'issued_at' => now(),
        ]);
    }
}
