<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\ApplicantProfile;
use App\Models\User;

class UpdateProfileAction
{
    public static function run(User $user, array $data): ApplicantProfile
    {
        $profile = $user->applicantProfile;

        abort_if($profile === null, 422, 'Profile not found.');

        $profile->update([
            'first_name' => $data['firstName'],
            'last_name' => $data['lastName'],
            'middle_name' => $data['middleName'] ?? null,
            'date_of_birth' => $data['dateOfBirth'],
            'gender' => $data['gender'],
            'nationality_id' => $data['nationalityId'],
            'country_of_residence_id' => $data['countryOfResidenceId'],
            'passport_number' => $data['passportNumber'],
            'passport_expiry_date' => $data['passportExpiryDate'],
            'phone' => $data['phone'],
            'address_line_1' => $data['addressLine1'],
            'address_line_2' => $data['addressLine2'] ?? null,
            'city' => $data['city'],
            'state' => $data['state'] ?? null,
            'postal_code' => $data['postalCode'] ?? null,
        ]);

        return $profile->refresh();
    }
}
