<?php

namespace App\Livewire\Profile;

use App\Domain\Identity\Actions\UpdatePasswordAction;
use App\Domain\Identity\Actions\UpdateProfileAction;
use App\Domain\Identity\Models\Country;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

class ProfilePage extends Component
{
    public string $firstName = '';

    public string $lastName = '';

    public string $middleName = '';

    public string $dateOfBirth = '';

    public string $gender = '';

    public string $nationalityId = '';

    public string $countryOfResidenceId = '';

    public string $passportNumber = '';

    public string $passportExpiryDate = '';

    public string $phone = '';

    public string $addressLine1 = '';

    public string $addressLine2 = '';

    public string $city = '';

    public string $state = '';

    public string $postalCode = '';

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    public bool $notifyEmailApplicationUpdates = true;

    public bool $notifyEmailPaymentReceipts = true;

    public bool $notifyEmailDocumentReminders = true;

    public function mount(): void
    {
        $profile = auth()->user()->applicantProfile;

        if (! $profile) {
            return;
        }

        $this->fill([
            'firstName' => $profile->first_name,
            'lastName' => $profile->last_name,
            'middleName' => $profile->middle_name ?? '',
            'dateOfBirth' => $profile->date_of_birth?->format('Y-m-d') ?? '',
            'gender' => $profile->gender,
            'nationalityId' => (string) $profile->nationality_id,
            'countryOfResidenceId' => (string) $profile->country_of_residence_id,
            'passportNumber' => $profile->passport_number ?? '',
            'passportExpiryDate' => $profile->passport_expiry_date?->format('Y-m-d') ?? '',
            'phone' => $profile->phone ?? '',
            'addressLine1' => $profile->address_line_1,
            'addressLine2' => $profile->address_line_2 ?? '',
            'city' => $profile->city,
            'state' => $profile->state ?? '',
            'postalCode' => $profile->postal_code ?? '',
        ]);

        $prefs = $profile->notification_preferences ?? [];
        $this->notifyEmailApplicationUpdates = $prefs['email_application_updates'] ?? true;
        $this->notifyEmailPaymentReceipts = $prefs['email_payment_receipts'] ?? true;
        $this->notifyEmailDocumentReminders = $prefs['email_document_reminders'] ?? true;
    }

    public function saveProfile(): void
    {
        $this->validate([
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'middleName' => ['nullable', 'string', 'max:100'],
            'dateOfBirth' => ['required', 'date', 'before:-18 years'],
            'gender' => ['required', 'in:male,female,other'],
            'nationalityId' => ['required', 'exists:countries,id'],
            'countryOfResidenceId' => ['required', 'exists:countries,id'],
            'passportNumber' => ['required', 'string', 'max:20'],
            'passportExpiryDate' => ['required', 'date', 'after:today'],
            'phone' => ['required', 'string', 'max:30'],
            'addressLine1' => ['required', 'string', 'max:255'],
            'addressLine2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postalCode' => ['nullable', 'string', 'max:20'],
        ]);

        UpdateProfileAction::run(auth()->user(), [
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'middleName' => $this->middleName ?: null,
            'dateOfBirth' => $this->dateOfBirth,
            'gender' => $this->gender,
            'nationalityId' => $this->nationalityId,
            'countryOfResidenceId' => $this->countryOfResidenceId,
            'passportNumber' => $this->passportNumber,
            'passportExpiryDate' => $this->passportExpiryDate,
            'phone' => $this->phone,
            'addressLine1' => $this->addressLine1,
            'addressLine2' => $this->addressLine2 ?: null,
            'city' => $this->city,
            'state' => $this->state ?: null,
            'postalCode' => $this->postalCode ?: null,
        ]);

        $this->dispatch('profile-saved');
    }

    public function changePassword(): void
    {
        $this->validate([
            'currentPassword' => ['required', 'string'],
            'newPassword' => ['required', 'string', 'min:8', 'same:newPasswordConfirmation'],
        ]);

        try {
            UpdatePasswordAction::run(auth()->user(), $this->currentPassword, $this->newPassword);
            $this->reset('currentPassword', 'newPassword', 'newPasswordConfirmation');
            $this->dispatch('password-changed');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());
        }
    }

    public function saveNotificationPreferences(): void
    {
        auth()->user()->applicantProfile->update([
            'notification_preferences' => [
                'email_application_updates' => $this->notifyEmailApplicationUpdates,
                'email_payment_receipts' => $this->notifyEmailPaymentReceipts,
                'email_document_reminders' => $this->notifyEmailDocumentReminders,
            ],
        ]);

        $this->dispatch('prefs-saved');
    }

    public function render(): View
    {
        $countries = Country::orderBy('name')->get(['id', 'name']);

        return view('livewire.profile.profile-page', [
            'countries' => $countries,
            'user' => auth()->user(),
        ])->layout('layouts.app', ['title' => 'My Profile']);
    }
}
