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
            ->greeting('Dear '.$notifiable->name.',')
            ->line('Additional information is required for your application ('.$this->application->tracking_number.').')
            ->line($this->message)
            ->action('View Application', url('/'));
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
