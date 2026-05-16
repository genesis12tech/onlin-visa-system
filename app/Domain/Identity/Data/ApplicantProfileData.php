<?php

namespace App\Domain\Identity\Data;

readonly class ApplicantProfileData
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $middleName,
        public string $dateOfBirth,
        public string $gender,
        public int $nationalityId,
        public int $countryOfResidenceId,
        public string $passportNumber,
        public string $passportExpiryDate,
        public string $phone,
        public string $addressLine1,
        public ?string $addressLine2,
        public string $city,
        public ?string $state,
        public ?string $postalCode,
    ) {}
}
