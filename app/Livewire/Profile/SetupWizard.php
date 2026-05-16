<?php

namespace App\Livewire\Profile;

use App\Domain\Identity\Actions\CompleteApplicantProfile;
use App\Domain\Identity\Data\ApplicantProfileData;
use App\Domain\Identity\Models\Country;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

class SetupWizard extends Component
{
    public int $currentStep = 1;

    public bool $saved = false;

    // Step 1 — personal info
    public string $firstName = '';

    public string $lastName = '';

    public string $middleName = '';

    public string $dateOfBirth = '';

    public string $gender = '';

    public string $nationalityId = '';

    public string $countryOfResidenceId = '';

    // Step 2 — passport + contact
    public string $passportNumber = '';

    public string $passportExpiryDate = '';

    public string $phone = '';

    public string $addressLine1 = '';

    public string $addressLine2 = '';

    public string $city = '';

    public string $state = '';

    public string $postalCode = '';

    /** @var array<string, array<int, mixed>> */
    protected array $stepRules = [
        1 => [
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'middleName' => ['nullable', 'string', 'max:100'],
            'dateOfBirth' => ['required', 'date', 'before:-18 years'],
            'gender' => ['required', 'in:male,female,other'],
            'nationalityId' => ['required', 'exists:countries,id'],
            'countryOfResidenceId' => ['required', 'exists:countries,id'],
        ],
        2 => [
            'passportNumber' => ['required', 'string', 'max:20'],
            'passportExpiryDate' => ['required', 'date', 'after:today'],
            'phone' => ['required', 'string', 'max:30'],
            'addressLine1' => ['required', 'string', 'max:255'],
            'addressLine2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postalCode' => ['nullable', 'string', 'max:20'],
        ],
    ];

    public function mount(): void
    {
        $profile = auth()->user()->applicantProfile;

        if ($profile) {
            $this->fill([
                'firstName' => $profile->first_name,
                'lastName' => $profile->last_name,
                'middleName' => $profile->middle_name ?? '',
                'dateOfBirth' => $profile->date_of_birth?->format('Y-m-d') ?? '',
                'gender' => $profile->gender,
                'nationalityId' => (string) $profile->nationality_id,
                'countryOfResidenceId' => (string) $profile->country_of_residence_id,
                'passportNumber' => $profile->passport_number,
                'passportExpiryDate' => $profile->passport_expiry_date?->format('Y-m-d') ?? '',
                'phone' => $profile->phone,
                'addressLine1' => $profile->address_line_1,
                'addressLine2' => $profile->address_line_2 ?? '',
                'city' => $profile->city,
                'state' => $profile->state ?? '',
                'postalCode' => $profile->postal_code ?? '',
            ]);
        }
    }

    public function nextStep(): void
    {
        $this->validate($this->stepRules[$this->currentStep]);
        $this->saveCurrentStep();
        $this->currentStep = 2;
    }

    public function complete(): void
    {
        $this->validate($this->stepRules[2]);
        $this->saveCurrentStep();
        $this->redirect(route('dashboard'));
    }

    private function saveCurrentStep(): void
    {
        $data = new ApplicantProfileData(
            firstName: $this->firstName,
            lastName: $this->lastName,
            middleName: $this->middleName ?: null,
            dateOfBirth: $this->dateOfBirth,
            gender: $this->gender,
            nationalityId: (int) $this->nationalityId,
            countryOfResidenceId: (int) $this->countryOfResidenceId,
            passportNumber: $this->passportNumber ?: 'PENDING',
            passportExpiryDate: $this->passportExpiryDate ?: '2099-01-01',
            phone: $this->phone ?: 'PENDING',
            addressLine1: $this->addressLine1 ?: 'PENDING',
            addressLine2: $this->addressLine2 ?: null,
            city: $this->city ?: 'PENDING',
            state: $this->state ?: null,
            postalCode: $this->postalCode ?: null,
        );

        CompleteApplicantProfile::run(auth()->user(), $data);

        $this->saved = true;
        $this->dispatch('saved');
    }

    public function countries(): Collection
    {
        return Country::where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function render(): View
    {
        return view('livewire.profile.setup-wizard', [
            'countries' => $this->countries(),
        ])->layout('layouts.app', ['title' => 'Profile Setup']);
    }
}
