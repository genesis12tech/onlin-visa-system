<?php

namespace App\Policies;

use App\Domain\Applications\Models\VisaType;
use App\Models\User;

class VisaTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin']);
    }

    public function view(User $user, VisaType $visaType): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin']);
    }

    public function update(User $user, VisaType $visaType): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin']);
    }

    public function delete(User $user, VisaType $visaType): bool
    {
        return $user->hasRole('super_admin');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }
}
