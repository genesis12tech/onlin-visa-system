<?php

namespace App\Notifications;

use App\Domain\Applications\Models\ApplicationAppointment;
use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentScheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public array $backoff = [5, 15, 30];

    public function __construct(
        public readonly VisaApplication $application,
        public readonly ApplicationAppointment $appointment,
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
            ->subject('Appointment scheduled for your visa application')
            ->markdown('emails.appointment-scheduled', [
                'applicantName' => $notifiable->name,
                'trackingNumber' => $this->application->tracking_number,
                'appointmentAt' => $this->appointment->appointment_at->format('l, F j, Y \a\t g:i A'),
                'location' => $this->appointment->location,
                'instructions' => $this->appointment->instructions,
                'applicationUrl' => route('applications.wizard', $this->application->tracking_number),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'appointment_scheduled',
            'visa_application_id' => $this->application->ulid,
            'tracking_number' => $this->application->tracking_number,
            'appointment_at' => $this->appointment->appointment_at->toIso8601String(),
            'location' => $this->appointment->location,
            'message' => 'Your appointment has been scheduled for '
                .$this->appointment->appointment_at->format('d M Y \a\t g:i A')
                .($this->appointment->location ? ' at '.$this->appointment->location : '').'.',
        ];
    }
}
