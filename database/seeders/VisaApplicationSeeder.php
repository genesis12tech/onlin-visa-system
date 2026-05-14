<?php

namespace Database\Seeders;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Domain\Reporting\Models\DailyApplicationMetrics;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class VisaApplicationSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'superadmin@example.com')->firstOrFail();
        $officer = User::where('email', 'officer@example.com')->firstOrFail();

        $touristType = VisaType::where('code', 'TOURIST_30')->firstOrFail();
        $tourist90Type = VisaType::where('code', 'TOURIST_90')->firstOrFail();
        $businessType = VisaType::where('code', 'BUSINESS_90')->firstOrFail();
        $studentType = VisaType::where('code', 'STUDENT_365')->firstOrFail();

        $touristForm = FormTemplate::where('visa_type_id', $touristType->ulid)->firstOrFail();
        $tourist90Form = FormTemplate::where('visa_type_id', $tourist90Type->ulid)->firstOrFail();
        $businessForm = FormTemplate::where('visa_type_id', $businessType->ulid)->firstOrFail();
        $studentForm = FormTemplate::whereIn('visa_type_id', [$studentType->ulid, $touristType->ulid])->first()
            ?? $touristForm;

        $applications = [
            [
                'reference' => 'VA-2024-A1F3K2',
                'first_name' => 'Arjun',
                'last_name' => 'Mehta',
                'email' => 'arjun.mehta@applicant.test',
                'nationality' => 'IN',
                'visa_type' => $touristType,
                'form' => $touristForm,
                'travel_date' => '2025-01-14',
                'status' => ApplicationStatus::Submitted,
                'assigned_to' => null,
                'submitted_at' => now()->subDays(30),
            ],
            [
                'reference' => 'VA-2024-B2G4L3',
                'first_name' => 'Sofia',
                'last_name' => 'Chen',
                'email' => 'sofia.chen@applicant.test',
                'nationality' => 'CN',
                'visa_type' => $studentType,
                'form' => $studentForm,
                'travel_date' => '2025-02-01',
                'status' => ApplicationStatus::UnderReview,
                'assigned_to' => $officer,
                'submitted_at' => now()->subDays(25),
            ],
            [
                'reference' => 'VA-2024-C3H5M4',
                'first_name' => 'James',
                'last_name' => 'Okonkwo',
                'email' => 'james.okonkwo@applicant.test',
                'nationality' => 'NG',
                'visa_type' => $tourist90Type,
                'form' => $tourist90Form,
                'travel_date' => '2025-01-20',
                'status' => ApplicationStatus::Approved,
                'assigned_to' => $officer,
                'submitted_at' => now()->subDays(20),
            ],
            [
                'reference' => 'VA-2024-D4I6N5',
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'email' => 'maria.santos@applicant.test',
                'nationality' => 'BR',
                'visa_type' => $touristType,
                'form' => $touristForm,
                'travel_date' => '2025-03-05',
                'status' => ApplicationStatus::DocsRequired,
                'assigned_to' => $officer,
                'submitted_at' => now()->subDays(18),
            ],
            [
                'reference' => 'VA-2024-E5J7O6',
                'first_name' => 'Ahmed',
                'last_name' => 'Al-Rashid',
                'email' => 'ahmed.alrashid@applicant.test',
                'nationality' => 'SA',
                'visa_type' => $businessType,
                'form' => $businessForm,
                'travel_date' => '2025-01-30',
                'status' => ApplicationStatus::UnderReview,
                'assigned_to' => $officer,
                'submitted_at' => now()->subDays(15),
            ],
            [
                'reference' => 'VA-2024-F6K8P7',
                'first_name' => 'Priya',
                'last_name' => 'Nair',
                'email' => 'priya.nair@applicant.test',
                'nationality' => 'IN',
                'visa_type' => $studentType,
                'form' => $studentForm,
                'travel_date' => '2025-02-15',
                'status' => ApplicationStatus::Submitted,
                'assigned_to' => null,
                'submitted_at' => now()->subDays(12),
            ],
            [
                'reference' => 'VA-2024-G7L9Q8',
                'first_name' => 'Lucas',
                'last_name' => 'Müller',
                'email' => 'lucas.muller@applicant.test',
                'nationality' => 'DE',
                'visa_type' => $touristType,
                'form' => $touristForm,
                'travel_date' => '2024-12-28',
                'status' => ApplicationStatus::Approved,
                'assigned_to' => $officer,
                'submitted_at' => now()->subDays(10),
            ],
            [
                'reference' => 'VA-2024-H8M0R9',
                'first_name' => 'Yuki',
                'last_name' => 'Tanaka',
                'email' => 'yuki.tanaka@applicant.test',
                'nationality' => 'JP',
                'visa_type' => $touristType,
                'form' => $touristForm,
                'travel_date' => '2025-01-10',
                'status' => ApplicationStatus::Rejected,
                'assigned_to' => $officer,
                'submitted_at' => now()->subDays(8),
            ],
            [
                'reference' => 'VA-2024-I9N1S0',
                'first_name' => 'Emma',
                'last_name' => 'Wilson',
                'email' => 'emma.wilson@applicant.test',
                'nationality' => 'AU',
                'visa_type' => $tourist90Type,
                'form' => $tourist90Form,
                'travel_date' => '2025-02-05',
                'status' => ApplicationStatus::Submitted,
                'assigned_to' => null,
                'submitted_at' => now()->subDays(5),
            ],
            [
                'reference' => 'VA-2024-J0O2T1',
                'first_name' => 'Ravi',
                'last_name' => 'Krishnan',
                'email' => 'ravi.krishnan@applicant.test',
                'nationality' => 'IN',
                'visa_type' => $businessType,
                'form' => $businessForm,
                'travel_date' => '2025-01-22',
                'status' => ApplicationStatus::UnderReview,
                'assigned_to' => $officer,
                'submitted_at' => now()->subDays(3),
            ],
        ];

        foreach ($applications as $data) {
            $this->createApplication($data, $admin);
        }

        $this->seedDailyMetrics($touristType->ulid);
    }

    private function createApplication(array $data, User $admin): void
    {
        $nationality = Country::where('iso2', $data['nationality'])->firstOrFail();
        $residence = Country::where('iso2', 'GB')->firstOrFail();

        $user = User::firstOrCreate(
            ['email' => $data['email']],
            [
                'name' => "{$data['first_name']} {$data['last_name']}",
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        if (! $user->hasRole('applicant')) {
            $user->assignRole('applicant');
        }

        $profile = ApplicantProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'date_of_birth' => '1990-01-01',
                'gender' => 'male',
                'nationality_id' => $nationality->id,
                'country_of_residence_id' => $residence->id,
                'passport_number' => strtoupper(Str::random(9)),
                'passport_expiry_date' => '2030-01-01',
                'phone' => '+1234567890',
                'address_line_1' => '123 Sample Street',
                'city' => 'London',
            ],
        );

        if (VisaApplication::where('tracking_number', $data['reference'])->exists()) {
            return;
        }

        $application = VisaApplication::create([
            'tracking_number' => $data['reference'],
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $data['visa_type']->ulid,
            'form_template_id' => $data['form']->ulid,
            'status' => $data['status'],
            'assigned_officer_id' => $data['assigned_to']?->id,
            'submitted_at' => $data['submitted_at'],
            'travel_date' => $data['travel_date'],
            'decision_at' => in_array($data['status'], [ApplicationStatus::Approved, ApplicationStatus::Rejected])
                ? now()->subDays(1)
                : null,
        ]);

        ApplicationStatusHistory::create([
            'visa_application_id' => $application->ulid,
            'from_status' => null,
            'to_status' => ApplicationStatus::Draft->value,
            'actor_id' => $admin->id,
            'created_at' => $data['submitted_at']->subHour(),
        ]);

        ApplicationStatusHistory::create([
            'visa_application_id' => $application->ulid,
            'from_status' => ApplicationStatus::Draft->value,
            'to_status' => ApplicationStatus::Submitted->value,
            'actor_id' => $admin->id,
            'created_at' => $data['submitted_at'],
        ]);

        if ($data['status'] !== ApplicationStatus::Submitted) {
            ApplicationStatusHistory::create([
                'visa_application_id' => $application->ulid,
                'from_status' => ApplicationStatus::Submitted->value,
                'to_status' => $data['status']->value,
                'actor_id' => $data['assigned_to']?->id ?? $admin->id,
                'created_at' => $data['submitted_at']->addDay(),
            ]);
        }
    }

    private function seedDailyMetrics(string $touristTypeUlid): void
    {
        $monthlyData = [
            ['offset' => 5, 'submitted' => 98,  'approved' => 60, 'rejected' => 8,  'pending' => 30],
            ['offset' => 4, 'submitted' => 145, 'approved' => 95, 'rejected' => 12, 'pending' => 38],
            ['offset' => 3, 'submitted' => 112, 'approved' => 72, 'rejected' => 9,  'pending' => 31],
            ['offset' => 2, 'submitted' => 189, 'approved' => 130, 'rejected' => 15, 'pending' => 44],
            ['offset' => 1, 'submitted' => 204, 'approved' => 148, 'rejected' => 18, 'pending' => 38],
            ['offset' => 0, 'submitted' => 312, 'approved' => 210, 'rejected' => 26, 'pending' => 76],
        ];

        foreach ($monthlyData as $row) {
            DailyApplicationMetrics::updateOrCreate(
                [
                    'date' => now()->subMonths($row['offset'])->startOfMonth()->toDateString(),
                    'visa_type_id' => $touristTypeUlid,
                ],
                [
                    'submitted_count' => $row['submitted'],
                    'approved_count' => $row['approved'],
                    'rejected_count' => $row['rejected'],
                    'pending_count' => $row['pending'],
                ],
            );
        }
    }
}
