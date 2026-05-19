<?php

namespace App\Domain\Identity\Actions;

use App\Models\User;

final class AssignRole
{
    public static function run(User $target, string $role, User $actor): User
    {
        $oldRoles = $target->getRoleNames()->toArray();

        $target->syncRoles([$role]);

        activity()
            ->causedBy($actor)
            ->performedOn($target)
            ->withProperties(['from' => $oldRoles, 'to' => $role])
            ->log('admin.role_assigned');

        return $target->refresh();
    }
}
