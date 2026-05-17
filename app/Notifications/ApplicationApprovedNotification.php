<?php

namespace App\Notifications;

use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public array $backoff = [5, 15, 30];

    public function __construct(public readonly VisaApplication $application)
    {
        $this->queue = 'emails';
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your visa application has been approved')
            ->markdown('emails.application-approved', [
                'applicantName' => $notifiable->name,
                'trackingNumber' => $this->application->tracking_number,
                'decisionAt' => $this->application->decision_at?->format('d M Y') ?? now()->format('d M Y'),
                'decisionReason' => $this->application->decision_reason,
                'dashboardUrl' => route('dashboard'),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'application_approved',
            'visa_application_id' => $this->application->ulid,
            'tracking_number' => $this->application->tracking_number,
            'message' => 'Your visa application ('.$this->application->tracking_number.') has been approved.',
        ];
    }
}
