<?php

namespace App\Policies;

use App\Domain\Identity\Models\ApplicantProfile;
use App\Models\User;

class ApplicantProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin', 'case_officer', 'senior_officer']);
    }

    public function view(User $user, ApplicantProfile $profile): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin', 'case_officer', 'senior_officer'])
            || $user->id === $profile->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ApplicantProfile $profile): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin'])
            || $user->id === $profile->user_id;
    }

    public function delete(User $user, ApplicantProfile $profile): bool
    {
        return $user->hasRole('super_admin');
    }
}
