<?php

namespace App\Livewire;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

class ApplicantDashboard extends Component
{
    public Collection $applications;

    public int $totalCount = 0;

    public int $inProgressCount = 0;

    public int $actionNeededCount = 0;

    public int $approvedCount = 0;

    public ?VisaApplication $actionRequiredApp = null;

    /** @var array<int, array{icon: string, label: string, href: string}> */
    public array $quickActions = [];

    public function mount(): void
    {
        $this->applications = collect();
        $profile = auth()->user()->applicantProfile;

        if (! $profile) {
            return;
        }

        $apps = VisaApplication::where('applicant_profile_id', $profile->ulid)
            ->with([
                'visaType',
                'visaType.country',
                'documents' => fn ($q) => $q->with('documentType'),
            ])
            ->orderByDesc('updated_at')
            ->get();

        $this->applications = $apps;
        $this->totalCount = $apps->count();
        $this->inProgressCount = $apps
            ->filter(fn ($a) => in_array($a->status->value, ApplicationStatus::inProgressValues(), true))
            ->count();
        $this->actionNeededCount = $apps
            ->filter(fn ($a) => $this->isActionRequired($a))
            ->count();
        $this->approvedCount = $apps
            ->filter(fn ($a) => $a->status === ApplicationStatus::Approved)
            ->count();
        $this->actionRequiredApp = $apps->first(fn ($a) => $this->isActionRequired($a));
        $this->quickActions = $this->buildQuickActions($apps);
    }

    private function isActionRequired(VisaApplication $app): bool
    {
        return $app->status === ApplicationStatus::AdditionalInfoRequested
            || $app->status === ApplicationStatus::PaymentPending
            || ($app->status === ApplicationStatus::Approved && $app->decision_letter_pdf_path !== null);
    }

    /** @param Collection<int, VisaApplication> $apps */
    private function buildQuickActions(Collection $apps): array
    {
        $actions = [];

        $infoApp = $apps->first(fn ($a) => $a->status === ApplicationStatus::AdditionalInfoRequested);
        if ($infoApp) {
            $actions[] = [
                'icon' => 'upload',
                'label' => 'Resubmit documents',
                'href' => route('applications.wizard', $infoApp->tracking_number),
            ];
        }

        $paymentApp = $apps->first(fn ($a) => $a->status === ApplicationStatus::PaymentPending);
        if ($paymentApp) {
            $actions[] = [
                'icon' => 'credit-card',
                'label' => 'Complete payment',
                'href' => route('applications.pay', $paymentApp->tracking_number),
            ];
        }

        $approvedApp = $apps->first(fn ($a) => $a->status === ApplicationStatus::Approved);
        if ($approvedApp) {
            $actions[] = [
                'icon' => 'download',
                'label' => 'Download decision letter',
                'href' => route('applications.wizard', $approvedApp->tracking_number),
            ];
        }

        $actions[] = [
            'icon' => 'search',
            'label' => 'Track an application',
            'href' => route('track'),
        ];

        return $actions;
    }

    public function render(): View
    {
        return view('livewire.applicant-dashboard')
            ->layout('layouts.app');
    }
}
