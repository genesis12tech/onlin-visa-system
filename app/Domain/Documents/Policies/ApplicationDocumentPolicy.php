<?php

namespace App\Domain\Documents\Policies;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Models\User;

class ApplicationDocumentPolicy
{
    public function view(User $user, ApplicationDocument $document): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin', 'senior_officer', 'case_officer', 'document_verifier', 'support_staff'])) {
            return true;
        }

        return $document->visaApplication->applicantProfile->user_id === $user->id;
    }

    public function upload(User $user, ApplicationDocument $document): bool
    {
        if ($document->visaApplication->applicantProfile->user_id !== $user->id) {
            return false;
        }

        return in_array($document->visaApplication->status, [
            ApplicationStatus::Draft,
            ApplicationStatus::DocsRequired,
        ]);
    }

    public function accept(User $user, ApplicationDocument $document): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'senior_officer', 'case_officer', 'document_verifier']);
    }

    public function reject(User $user, ApplicationDocument $document): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'senior_officer', 'case_officer', 'document_verifier']);
    }
}
