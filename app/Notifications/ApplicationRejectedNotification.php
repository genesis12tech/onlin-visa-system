<?php

namespace App\Notifications;

use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $queue = 'emails';

    public function __construct(public readonly VisaApplication $application) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your visa application decision')
            ->greeting('Dear '.$notifiable->name.',')
            ->line('We regret to inform you that your visa application ('.$this->application->tracking_number.') has been rejected.')
            ->when(
                $this->application->decision_reason,
                fn ($m) => $m->line('Reason: '.$this->application->decision_reason)
            )
            ->action('View Application', url('/'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'application_rejected',
            'visa_application_id' => $this->application->ulid,
            'tracking_number' => $this->application->tracking_number,
            'message' => 'Your application ('.$this->application->tracking_number.') has been rejected.',
        ];
    }
}
