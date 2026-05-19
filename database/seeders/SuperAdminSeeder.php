<?php

namespace Database\Seeders;

use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPER_ADMIN_EMAIL');
        $password = env('SUPER_ADMIN_PASSWORD');
        $name = env('SUPER_ADMIN_NAME', 'Super Admin');

        if (! $email || ! $password) {
            $this->command->warn(
                'SUPER_ADMIN_EMAIL or SUPER_ADMIN_PASSWORD not set — skipping SuperAdminSeeder.'
            );

            return;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'status' => UserStatus::Active->value,
            ]
        );

        $user->syncRoles(['super_admin']);
    }
}
