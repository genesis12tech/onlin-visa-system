<?php

namespace App\Policies;

use App\Domain\Payments\Models\VisaFee;
use App\Models\User;

class VisaFeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin', 'finance_officer']);
    }

    public function view(User $user, VisaFee $visaFee): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin', 'finance_officer']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin']);
    }

    public function update(User $user, VisaFee $visaFee): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin']);
    }

    public function delete(User $user, VisaFee $visaFee): bool
    {
        return $user->hasRole('super_admin');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }
}
