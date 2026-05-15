<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Models\User;
use App\Notifications\DocumentRejectedNotification;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

class RejectDocument
{
    public function execute(ApplicationDocument $document, User $officer, string $reason): ApplicationDocument
    {
        DB::transaction(function () use ($document, $officer, $reason) {
            $document->update([
                'status' => DocumentStatus::Rejected,
                'reviewed_by' => $officer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            AuditLogger::log('document.rejected', $document, ['reason' => $reason], $officer->id);
        });

        $document->refresh();
        $document->load('visaApplication.applicantProfile.user', 'documentType');

        $applicantUser = $document->visaApplication?->applicantProfile?->user;

        if ($applicantUser) {
            $applicantUser->notify(new DocumentRejectedNotification($document, $reason));
        }

        return $document;
    }
}
