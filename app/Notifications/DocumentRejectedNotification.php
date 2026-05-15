<?php

namespace App\Notifications;

use App\Domain\Documents\Models\ApplicationDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public array $backoff = [5, 15, 30];

    public function __construct(
        public readonly ApplicationDocument $document,
        public readonly string $reason,
    ) {
        $this->queue = 'emails';
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $documentTypeName = $this->document->documentType->name ?? 'document';
        $trackingNumber = $this->document->visaApplication?->tracking_number ?? '—';

        return (new MailMessage)
            ->subject('Document rejected — action required')
            ->greeting('Dear '.$notifiable->name.',')
            ->line('Your '.$documentTypeName.' for application ('.$trackingNumber.') has been rejected.')
            ->line('Reason: '.$this->reason)
            ->line('Please upload a replacement document to continue your application.')
            ->action('Upload Document', url('/'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'document_rejected',
            'application_document_id' => $this->document->ulid,
            'visa_application_id' => $this->document->visa_application_id,
            'document_type' => $this->document->documentType->name ?? null,
            'reason' => $this->reason,
            'message' => 'Your document has been rejected: '.$this->reason,
        ];
    }
}
