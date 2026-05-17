<?php

namespace App\Notifications;

use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdditionalInfoRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public array $backoff = [5, 15, 30];

    public function __construct(
        public readonly VisaApplication $application,
        public readonly string $message,
    ) {
        $this->queue = 'emails';
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Additional information requested for your visa application')
            ->markdown('emails.additional-info-requested', [
                'applicantName' => $notifiable->name,
                'trackingNumber' => $this->application->tracking_number,
                'officerMessage' => $this->message,
                'applicationUrl' => route('applications.wizard', $this->application->tracking_number),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'additional_info_requested',
            'visa_application_id' => $this->application->ulid,
            'tracking_number' => $this->application->tracking_number,
            'message' => $this->message,
        ];
    }
}
