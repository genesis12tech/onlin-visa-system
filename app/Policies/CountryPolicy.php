<?php

namespace App\Policies;

use App\Domain\Identity\Models\Country;
use App\Models\User;

class CountryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin']);
    }

    public function view(User $user, Country $country): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin']);
    }

    public function update(User $user, Country $country): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin']);
    }

    public function delete(User $user, Country $country): bool
    {
        return $user->hasRole('super_admin');
    }
}
