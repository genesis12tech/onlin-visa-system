<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Data\ApplicantProfileData;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Models\User;

class CompleteApplicantProfile
{
    public static function run(User $user, ApplicantProfileData $data): ApplicantProfile
    {
        return ApplicantProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_name' => $data->firstName,
                'last_name' => $data->lastName,
                'middle_name' => $data->middleName,
                'date_of_birth' => $data->dateOfBirth,
                'gender' => $data->gender,
                'nationality_id' => $data->nationalityId,
                'country_of_residence_id' => $data->countryOfResidenceId,
                'passport_number' => $data->passportNumber,
                'passport_expiry_date' => $data->passportExpiryDate,
                'phone' => $data->phone,
                'address_line_1' => $data->addressLine1,
                'address_line_2' => $data->addressLine2,
                'city' => $data->city,
                'state' => $data->state,
                'postal_code' => $data->postalCode,
            ]
        );
    }
}
