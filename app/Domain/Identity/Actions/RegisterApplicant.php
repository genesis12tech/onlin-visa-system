<?php

namespace App\Domain\Identity\Actions;

use App\Models\User;

class RegisterApplicant
{
    public static function run(string $name, string $email, string $password): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'two_factor_enabled' => false,
        ]);

        $user->assignRole('applicant');

        return $user;
    }
}
