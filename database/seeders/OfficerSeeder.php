<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\OfficerProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OfficerSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $officers = [
            [
                'name' => 'Priya Mehta',
                'email' => 'priya.mehta@example.com',
                'officer_id' => 'OFF-001',
                'initials' => 'PM',
                'color' => 'indigo',
                'specs' => ['Tourist', 'Business'],
            ],
            [
                'name' => 'Rahul Sharma',
                'email' => 'rahul.sharma@example.com',
                'officer_id' => 'OFF-002',
                'initials' => 'RS',
                'color' => 'blue',
                'specs' => ['Work', 'Student'],
            ],
            [
                'name' => 'Anita Desai',
                'email' => 'anita.desai@example.com',
                'officer_id' => 'OFF-003',
                'initials' => 'AD',
                'color' => 'green',
                'specs' => ['Medical', 'Transit'],
            ],
            [
                'name' => 'Mohammed Khan',
                'email' => 'mohammed.khan@example.com',
                'officer_id' => 'OFF-004',
                'initials' => 'MK',
                'color' => 'amber',
                'specs' => ['Business', 'Work'],
            ],
        ];

        foreach ($officers as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'officer_id' => $data['officer_id'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            if (! $user->hasRole('case_officer')) {
                $user->syncRoles(['case_officer']);
            }

            OfficerProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'display_initials' => $data['initials'],
                    'capacity' => 12,
                    'specialisations' => $data['specs'],
                    'avatar_color' => $data['color'],
                    'is_accepting_assignments' => true,
                ]
            );
        }
    }
}
