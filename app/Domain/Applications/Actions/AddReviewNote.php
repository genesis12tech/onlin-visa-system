<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Models\ApplicationNote;
use App\Domain\Applications\Models\VisaApplication;
use App\Models\User;
use App\Notifications\ReviewNoteAddedNotification;

class AddReviewNote
{
    public function execute(
        VisaApplication $application,
        User $author,
        string $body,
        bool $isVisibleToApplicant = false,
        array $metadata = [],
    ): ApplicationNote {
        $note = ApplicationNote::create([
            'visa_application_id' => $application->ulid,
            'author_id' => $author->id,
            'body' => $body,
            'is_visible_to_applicant' => $isVisibleToApplicant,
            'metadata' => $metadata ?: null,
        ]);

        if ($isVisibleToApplicant) {
            $application->load('applicantProfile.user');
            $applicant = $application->applicantProfile?->user;

            if ($applicant) {
                $applicant->notify(new ReviewNoteAddedNotification($application, $note));
            }
        }

        return $note;
    }
}
