<?php

namespace App\Domain\Applications\Policies;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use App\Models\User;

class VisaApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('applicant')) {
            return $user->applicantProfile !== null;
        }

        return $user->hasAnyRole(['super_admin', 'admin', 'senior_officer', 'case_officer', 'support_staff']);
    }

    public function view(User $user, VisaApplication $application): bool
    {
        if ($user->hasRole('applicant')) {
            return $user->applicantProfile?->ulid === $application->applicant_profile_id;
        }

        if ($user->hasAnyRole(['super_admin', 'admin', 'senior_officer', 'support_staff'])) {
            return true;
        }

        if ($user->hasRole('case_officer')) {
            return $application->assigned_officer_id === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('applicant') && $user->applicantProfile !== null;
    }

    public function update(User $user, VisaApplication $application): bool
    {
        return $user->hasRole('applicant')
            && $user->applicantProfile?->ulid === $application->applicant_profile_id
            && $application->status === ApplicationStatus::Draft;
    }

    public function submit(User $user, VisaApplication $application): bool
    {
        return $user->hasRole('applicant')
            && $user->applicantProfile?->ulid === $application->applicant_profile_id
            && $application->status === ApplicationStatus::Draft;
    }

    public function withdraw(User $user, VisaApplication $application): bool
    {
        if (! $user->hasRole('applicant')) {
            return false;
        }

        if ($user->applicantProfile?->ulid !== $application->applicant_profile_id) {
            return false;
        }

        return in_array($application->status, [
            ApplicationStatus::Draft,
            ApplicationStatus::Submitted,
            ApplicationStatus::PaymentPending,
            ApplicationStatus::PaymentCompleted,
            ApplicationStatus::UnderReview,
            ApplicationStatus::AdditionalInfoRequested,
        ], strict: true);
    }

    public function approve(User $user, VisaApplication $application): bool
    {
        if (! $user->hasAnyRole(['super_admin', 'admin', 'senior_officer', 'case_officer'])) {
            return false;
        }

        if (! in_array($application->status, [
            ApplicationStatus::UnderReview,
            ApplicationStatus::DocsRequired,
            ApplicationStatus::AdditionalInfoRequested,
        ], strict: true)) {
            return false;
        }

        if ($user->hasRole('case_officer')) {
            return $application->assigned_officer_id === $user->id;
        }

        return true;
    }

    public function reject(User $user, VisaApplication $application): bool
    {
        return $this->approve($user, $application);
    }

    public function reassign(User $user, VisaApplication $application): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'senior_officer']);
    }

    public function requestAdditionalInfo(User $user, VisaApplication $application): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin', 'senior_officer'])) {
            return true;
        }

        if ($user->hasRole('case_officer')) {
            return $application->assigned_officer_id === $user->id;
        }

        return false;
    }

    public function scheduleAppointment(User $user, VisaApplication $application): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin', 'senior_officer'])) {
            return true;
        }

        if ($user->hasRole('case_officer')) {
            return $application->assigned_officer_id === $user->id;
        }

        return false;
    }

    public function assign(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'senior_officer']);
    }

    public function export(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'finance_officer']);
    }
}
