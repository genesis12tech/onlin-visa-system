<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MfaOtpNotification extends Notification
{
    public function __construct(private readonly string $otp) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your sign-in code')
            ->line("Your one-time sign-in code is: **{$this->otp}**")
            ->line('This code expires in 15 minutes.')
            ->line('If you did not request this code, you can safely ignore this email.');
    }
}
