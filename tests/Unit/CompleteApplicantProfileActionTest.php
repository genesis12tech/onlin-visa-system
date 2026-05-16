<?php

namespace Tests\Unit;

use App\Domain\Identity\Actions\CompleteApplicantProfile;
use App\Domain\Identity\Data\ApplicantProfileData;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompleteApplicantProfileActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_profile_for_user(): void
    {
        $user = User::factory()->create();
        $country = Country::factory()->create();

        $data = new ApplicantProfileData(
            firstName: 'Jane',
            lastName: 'Doe',
            middleName: null,
            dateOfBirth: '1990-05-15',
            gender: 'female',
            nationalityId: $country->id,
            countryOfResidenceId: $country->id,
            passportNumber: 'AB1234567',
            passportExpiryDate: '2030-01-01',
            phone: '+44 7911 123456',
            addressLine1: '10 Downing Street',
            addressLine2: null,
            city: 'London',
            state: null,
            postalCode: 'SW1A 2AA',
        );

        $profile = CompleteApplicantProfile::run($user, $data);

        $this->assertInstanceOf(ApplicantProfile::class, $profile);
        $this->assertEquals($user->id, $profile->user_id);
        $this->assertEquals('Jane', $profile->first_name);
    }

    public function test_updates_existing_profile(): void
    {
        $user = User::factory()->create();
        $country = Country::factory()->create();
        ApplicantProfile::factory()->create([
            'user_id' => $user->id,
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
        ]);

        $data = new ApplicantProfileData(
            firstName: 'Updated',
            lastName: 'Name',
            middleName: null,
            dateOfBirth: '1990-05-15',
            gender: 'female',
            nationalityId: $country->id,
            countryOfResidenceId: $country->id,
            passportNumber: 'ZZ9876543',
            passportExpiryDate: '2032-01-01',
            phone: '+1 555 000 0000',
            addressLine1: '1 Main St',
            addressLine2: null,
            city: 'Springfield',
            state: 'IL',
            postalCode: '62701',
        );

        $profile = CompleteApplicantProfile::run($user, $data);

        $this->assertEquals('Updated', $profile->first_name);
        $this->assertEquals(1, ApplicantProfile::where('user_id', $user->id)->count());
    }

    public function test_encrypts_sensitive_fields(): void
    {
        $user = User::factory()->create();
        $country = Country::factory()->create();

        $data = new ApplicantProfileData(
            firstName: 'Jane',
            lastName: 'Doe',
            middleName: null,
            dateOfBirth: '1990-05-15',
            gender: 'female',
            nationalityId: $country->id,
            countryOfResidenceId: $country->id,
            passportNumber: 'SECRET123',
            passportExpiryDate: '2030-01-01',
            phone: '+44 secret',
            addressLine1: '1 St',
            addressLine2: null,
            city: 'London',
            state: null,
            postalCode: null,
        );

        CompleteApplicantProfile::run($user, $data);

        $raw = DB::table('applicant_profiles')
            ->where('user_id', $user->id)
            ->value('passport_number');

        $this->assertNotEquals('SECRET123', $raw);
    }
}
