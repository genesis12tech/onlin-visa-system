<?php

namespace App\Livewire\Tracking;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

class PublicTrackingForm extends Component
{
    public string $trackingNumber = '';

    public string $email = '';

    public ?array $result = null;

    public bool $notFound = false;

    protected array $rules = [
        'trackingNumber' => 'required|string|min:3|max:50',
        'email' => 'required|email|max:255',
    ];

    public function submit(): void
    {
        $this->notFound = false;
        $this->result = null;

        try {
            $this->validate();
        } catch (ValidationException) {
            $this->notFound = true;

            return;
        }

        $application = VisaApplication::where('tracking_number', $this->trackingNumber)
            ->with([
                'visaType',
                'applicantProfile.user',
                'statusHistories' => fn ($q) => $q->whereNotNull('public_label')->orderBy('created_at'),
            ])
            ->first();

        // Identical error for not-found vs wrong email (no enumeration)
        if ($application === null) {
            $this->notFound = true;

            return;
        }

        $applicantEmail = $application->applicantProfile?->user?->email;

        if (! $applicantEmail || strtolower($applicantEmail) !== strtolower(trim($this->email))) {
            $this->notFound = true;

            return;
        }

        $this->result = [
            'tracking_number' => $application->tracking_number,
            'visa_type_name' => $application->visaType->name,
            'status_value' => $application->status->value,
            'active_step' => $this->resolveActiveStep($application->status),
            'histories' => $application->statusHistories
                ->map(fn ($h) => [
                    'public_label' => $h->public_label,
                    'created_at' => $h->created_at->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    private function resolveActiveStep(ApplicationStatus $status): int
    {
        return match ($status) {
            ApplicationStatus::Draft,
            ApplicationStatus::Submitted,
            ApplicationStatus::PaymentPending,
            ApplicationStatus::PaymentCompleted => 1,
            ApplicationStatus::UnderReview,
            ApplicationStatus::AdditionalInfoRequested,
            ApplicationStatus::DocsRequired => 2,
            ApplicationStatus::Approved,
            ApplicationStatus::Rejected,
            ApplicationStatus::Withdrawn => 3,
        };
    }

    public function render(): View
    {
        return view('livewire.tracking.public-tracking-form')
            ->layout('layouts.guest', ['title' => 'Track Your Application']);
    }
}
