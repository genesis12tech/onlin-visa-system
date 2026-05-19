<?php

namespace App\Notifications;

use App\Domain\Applications\Models\ApplicationNote;
use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReviewNoteAddedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public array $backoff = [5, 15, 30];

    public function __construct(
        public readonly VisaApplication $application,
        public readonly ApplicationNote $note,
    ) {
        $this->queue = 'emails';
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'application_id' => $this->application->ulid,
            'tracking_number' => $this->application->tracking_number,
            'note_body' => $this->note->body,
        ];
    }
}
