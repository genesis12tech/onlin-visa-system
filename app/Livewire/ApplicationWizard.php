<?php

namespace App\Livewire;

use App\Domain\Applications\Actions\CreateDraftApplication;
use App\Domain\Applications\Actions\SubmitApplication;
use App\Domain\Applications\Actions\UpdateApplicationSection;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\ServiceLocation;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\Country;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ApplicationWizard extends Component
{
    public int $currentStep = 1;

    public bool $submitted = false;

    public string $selectedVisaTypeUlid = '';

    #[Locked]
    public string $applicationUlid = '';

    public string $trackingNumber = '';

    // Step 2 — Personal Info
    public string $firstName = '';

    public string $lastName = '';

    public string $dateOfBirth = '';

    public string $gender = '';

    public string $nationalityId = '';

    public string $passportNumber = '';

    public string $passportExpiry = '';

    public string $phone = '';

    public string $email = '';

    // Step 3 — Travel Details
    public string $arrivalDate = '';

    public string $departureDate = '';

    public string $portOfEntry = '';

    public string $accommodation = '';

    public string $purpose = '';

    public string $previouslyRefused = '';

    // Step 5 — Declaration
    public bool $declarationAccepted = false;

    /** @var array<int, array<string, array<int, mixed>>> */
    protected array $stepRules = [
        1 => [
            'selectedVisaTypeUlid' => ['required', 'exists:visa_types,ulid'],
        ],
        2 => [
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'dateOfBirth' => ['required', 'date', 'before:-18 years'],
            'gender' => ['required', 'in:male,female,other'],
            'nationalityId' => ['required', 'exists:countries,id'],
            'passportNumber' => ['required', 'string', 'max:20'],
            'passportExpiry' => ['required', 'date', 'after:+6 months'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
        ],
        3 => [
            'arrivalDate' => ['required', 'date', 'after:today'],
            'departureDate' => ['required', 'date', 'after:arrivalDate'],
            'portOfEntry' => ['required', 'string', 'max:255'],
            'accommodation' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'min:20', 'max:1000'],
            'previouslyRefused' => ['required', 'in:yes,no'],
        ],
    ];

    public function mount(): void
    {
        Gate::authorize('create', VisaApplication::class);

        $profile = auth()->user()->applicantProfile;

        if ($profile) {
            $this->fill([
                'firstName' => $profile->first_name ?? '',
                'lastName' => $profile->last_name ?? '',
                'dateOfBirth' => $profile->date_of_birth?->format('Y-m-d') ?? '',
                'gender' => $profile->gender ?? '',
                'nationalityId' => (string) ($profile->nationality_id ?? ''),
                'passportNumber' => $profile->passport_number ?? '',
                'passportExpiry' => $profile->passport_expiry_date?->format('Y-m-d') ?? '',
                'phone' => $profile->phone ?? '',
            ]);
        }

        $this->email = auth()->user()->email ?? '';
    }

    public function nextStep(): void
    {
        $rules = $this->stepRules[$this->currentStep] ?? [];

        if (! empty($rules)) {
            $this->validate($rules);
        }

        if ($this->currentStep === 1 && ! $this->createApplication()) {
            return;
        }

        if ($this->currentStep === 2) {
            $this->savePersonalInfo();
        }

        if ($this->currentStep === 3) {
            $this->saveTravelDetails();
        }

        $this->currentStep++;
    }

    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function submit(): void
    {
        $this->validate(['declarationAccepted' => ['accepted']]);

        $application = VisaApplication::findOrFail($this->applicationUlid);
        Gate::authorize('submit', $application);

        try {
            app(SubmitApplication::class)->execute($application, auth()->user());
        } catch (\RuntimeException $e) {
            $this->addError('submit', $e->getMessage());

            return;
        }

        $this->submitted = true;
    }

    /** @return Collection<int, VisaType> */
    public function visaTypes(): Collection
    {
        return VisaType::where('is_active', true)->orderBy('name')->get();
    }

    /** @return Collection<int, Country> */
    public function countries(): Collection
    {
        return Country::where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    /** @return Collection<int, ServiceLocation> */
    public function serviceLocations(): Collection
    {
        return ServiceLocation::active()->orderBy('name')->get();
    }

    public function render(): View
    {
        $application = $this->applicationUlid
            ? VisaApplication::with('visaType')->find($this->applicationUlid)
            : null;

        return view('livewire.application-wizard', [
            'visaTypes' => $this->visaTypes(),
            'countries' => $this->countries(),
            'serviceLocations' => $this->serviceLocations(),
            'application' => $application,
        ])->layout('layouts.app', ['title' => 'New Application']);
    }

    private function createApplication(): bool
    {
        $profile = auth()->user()->applicantProfile;
        $visaType = VisaType::findOrFail($this->selectedVisaTypeUlid);

        $existing = VisaApplication::where('applicant_profile_id', $profile->ulid)
            ->where('visa_type_id', $visaType->ulid)
            ->where('status', ApplicationStatus::Draft->value)
            ->first();

        if ($existing) {
            $this->applicationUlid = $existing->ulid;
            $this->trackingNumber = $existing->tracking_number;

            return true;
        }

        $formTemplate = FormTemplate::where('visa_type_id', $visaType->ulid)
            ->where('is_active', true)
            ->orderByDesc('version')
            ->first();

        if (! $formTemplate) {
            $this->addError('selectedVisaTypeUlid', 'This visa type is not accepting applications at this time.');

            return false;
        }

        $application = app(CreateDraftApplication::class)->execute(
            applicantProfile: $profile,
            visaType: $visaType,
            formTemplate: $formTemplate,
        );

        $this->applicationUlid = $application->ulid;
        $this->trackingNumber = $application->tracking_number;

        return true;
    }

    private function savePersonalInfo(): void
    {
        $application = VisaApplication::findOrFail($this->applicationUlid);
        $country = Country::find($this->nationalityId);

        UpdateApplicationSection::run($application, 'personal_info', [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'date_of_birth' => $this->dateOfBirth,
            'gender' => $this->gender,
            'nationality' => $country?->name ?? '',
            'passport_number' => $this->passportNumber,
            'passport_expiry' => $this->passportExpiry,
            'phone' => $this->phone,
            'email' => $this->email,
        ]);
    }

    private function saveTravelDetails(): void
    {
        $application = VisaApplication::findOrFail($this->applicationUlid);

        UpdateApplicationSection::run($application, 'travel_details', [
            'arrival_date' => $this->arrivalDate,
            'departure_date' => $this->departureDate,
            'port_of_entry' => $this->portOfEntry,
            'accommodation' => $this->accommodation,
            'purpose' => $this->purpose,
            'previously_refused' => $this->previouslyRefused,
        ]);
    }
}
