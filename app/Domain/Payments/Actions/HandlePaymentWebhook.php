<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Jobs\GenerateReceiptPdf;
use App\Domain\Payments\Models\Invoice;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentWebhookEvent;
use Illuminate\Support\Facades\DB;

class HandlePaymentWebhook
{
    public function execute(object $event): void
    {
        $webhookEvent = PaymentWebhookEvent::where('event_id', $event->id)->first();

        if ($webhookEvent?->processed_at !== null) {
            return;
        }

        if (! $webhookEvent) {
            $webhookEvent = PaymentWebhookEvent::create([
                'provider' => 'stripe',
                'event_id' => $event->id,
                'event_type' => $event->type,
                'payload' => json_decode(json_encode($event), true),
                'created_at' => now(),
            ]);
        }

        try {
            DB::transaction(function () use ($event, $webhookEvent): void {
                match ($event->type) {
                    'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
                    'payment_intent.payment_failed' => $this->handlePaymentFailed($event->data->object),
                    default => null,
                };

                $webhookEvent->markProcessed();
            });
        } catch (\Throwable $e) {
            $webhookEvent->markFailed($e->getMessage());
            throw $e;
        }
    }

    private function handleCheckoutCompleted(object $session): void
    {
        if (($session->payment_status ?? '') !== 'paid') {
            return;
        }

        $payment = Payment::where('provider_checkout_session_id', $session->id)->first();

        if (! $payment || $payment->status === PaymentStatus::Succeeded) {
            return;
        }

        $payment->update([
            'status' => PaymentStatus::Succeeded,
            'provider_payment_intent_id' => $session->payment_intent ?? null,
            'succeeded_at' => now(),
        ]);

        $application = $payment->visaApplication;

        $application->update(['status' => ApplicationStatus::PaymentCompleted]);

        ApplicationStatusHistory::create([
            'visa_application_id' => $application->ulid,
            'from_status' => ApplicationStatus::PaymentPending->value,
            'to_status' => ApplicationStatus::PaymentCompleted->value,
            'actor_id' => null,
            'created_at' => now(),
        ]);

        $invoice = Invoice::create([
            'payment_id' => $payment->ulid,
            'invoice_number' => $this->generateInvoiceNumber(),
            'issued_at' => now(),
        ]);

        GenerateReceiptPdf::dispatch($invoice->ulid)->onQueue('pdfs');
    }

    private function handlePaymentFailed(object $intent): void
    {
        $failureMessage = $intent->last_payment_error->message ?? 'Payment failed';

        $payment = Payment::where('provider_payment_intent_id', $intent->id)->first();

        if (! $payment) {
            return;
        }

        $payment->update([
            'status' => PaymentStatus::Failed,
            'failure_reason' => $failureMessage,
        ]);
    }

    private function generateInvoiceNumber(): string
    {
        $year = now()->format('Y');
        $count = Invoice::whereYear('created_at', $year)->count() + 1;

        return 'INV-'.$year.'-'.str_pad((string) $count, 6, '0', STR_PAD_LEFT);
    }
}
