<?php

namespace App\Notifications;

use App\Domain\Payments\Models\Invoice;
use App\Domain\Payments\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentSucceededNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public array $backoff = [5, 15, 30];

    public function __construct(
        public readonly Payment $payment,
        public readonly Invoice $invoice,
    ) {
        $this->queue = 'default';
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format($this->payment->amount_total / 100, 2).' '.strtoupper($this->payment->currency);
        $trackingNumber = $this->payment->visaApplication?->tracking_number ?? '—';

        return (new MailMessage)
            ->subject('Payment received — Invoice #'.$this->invoice->invoice_number)
            ->markdown('emails.payment-succeeded', [
                'applicantName' => $notifiable->name,
                'invoiceNumber' => $this->invoice->invoice_number,
                'amount' => $amount,
                'trackingNumber' => $trackingNumber,
                'receiptUrl' => route('invoices.receipt', $this->invoice->ulid),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_succeeded',
            'visa_application_id' => $this->payment->visaApplication?->ulid,
            'tracking_number' => $this->payment->visaApplication?->tracking_number,
            'invoice_number' => $this->invoice->invoice_number,
            'amount_total' => $this->payment->amount_total,
            'currency' => $this->payment->currency,
            'message' => 'Payment of '.number_format($this->payment->amount_total / 100, 2).' '.strtoupper($this->payment->currency).' received.',
        ];
    }
}
