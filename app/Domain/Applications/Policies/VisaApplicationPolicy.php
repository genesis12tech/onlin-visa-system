<?php

namespace App\Domain\Applications\Policies;

use App\Domain\Applications\Models\VisaApplication;
use App\Models\User;

class VisaApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'senior_officer', 'case_officer', 'support_staff']);
    }

    public function view(User $user, VisaApplication $application): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin', 'senior_officer', 'support_staff'])) {
            return true;
        }

        if ($user->hasRole('case_officer')) {
            return $application->assigned_officer_id === $user->id;
        }

        return false;
    }

    public function approve(User $user, VisaApplication $application): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'senior_officer']);
    }

    public function reject(User $user, VisaApplication $application): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'senior_officer']);
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
