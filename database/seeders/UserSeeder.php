<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Roles are assigned in Milestone 1 after spatie/laravel-permission is installed.
        // These users are created now so the app is functional for development and demos.

        $users = [
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'super_admin',
            ],
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'admin',
            ],
            [
                'name' => 'Case Officer',
                'email' => 'officer@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'case_officer',
            ],
            [
                'name' => 'Senior Officer',
                'email' => 'senior.officer@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'senior_officer',
            ],
            [
                'name' => 'Finance Officer',
                'email' => 'finance@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'finance_officer',
            ],
            [
                'name' => 'John Applicant',
                'email' => 'applicant@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'applicant',
            ],
        ];

        foreach ($users as $userData) {
            $role = $userData['role'];
            unset($userData['role']);

            $user = User::updateOrCreate(['email' => $userData['email']], $userData);
            $user->syncRoles([$role]);
        }
    }
}
