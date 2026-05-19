<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;

final class SuspendUser
{
    public static function run(User $target, User $actor): User
    {
        if ($target->hasRole('super_admin')) {
            throw new \RuntimeException('Super admins cannot be suspended.');
        }

        $target->update(['status' => UserStatus::Suspended]);

        activity()
            ->causedBy($actor)
            ->performedOn($target)
            ->withProperties([
                'from' => UserStatus::Active->value,
                'to' => UserStatus::Suspended->value,
            ])
            ->log('user.suspended');

        return $target->refresh();
    }
}
