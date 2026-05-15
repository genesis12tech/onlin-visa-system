<?php

namespace App\Notifications;

use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public array $backoff = [5, 15, 30];

    public function __construct(public readonly VisaApplication $application)
    {
        $this->queue = 'high';
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your visa application has been received')
            ->greeting('Dear '.$notifiable->name.',')
            ->line('We have received your visa application ('.$this->application->tracking_number.').')
            ->line('Your application is now under review. We will notify you of any updates.')
            ->action('Track Your Application', url('/'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'application_submitted',
            'visa_application_id' => $this->application->ulid,
            'tracking_number' => $this->application->tracking_number,
            'message' => 'Your application ('.$this->application->tracking_number.') has been received.',
        ];
    }
}
