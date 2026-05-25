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

    /** @var array{total:int,approved:int,underReview:int,pendingPayment:int} */
    public array $stats = ['total' => 0, 'approved' => 0, 'underReview' => 0, 'pendingPayment' => 0];

    public int $totalCount = 0;

    public int $inProgressCount = 0;

    public int $actionNeededCount = 0;

    public int $approvedCount = 0;

    public ?VisaApplication $actionRequiredApp = null;

    public ?VisaApplication $upcomingTrip = null;

    /** @var array<int, array{icon: string, label: string, href: string}> */
    public array $quickActions = [];

    public function mount(): void
    {
        $this->applications = collect();
        $profile = auth()->user()->applicantProfile;

        if (! $profile) {
            return;
        }

        $allApps = VisaApplication::where('applicant_profile_id', $profile->ulid)
            ->with([
                'visaType',
                'visaType.country',
                'documents' => fn ($q) => $q->with('documentType'),
            ])
            ->orderByDesc('updated_at')
            ->get();

        $this->applications = $allApps->take(3);

        $this->totalCount = $allApps->count();
        $this->approvedCount = $allApps->filter(fn ($a) => $a->status === ApplicationStatus::Approved)->count();
        $this->inProgressCount = $allApps
            ->filter(fn ($a) => in_array($a->status->value, ApplicationStatus::inProgressValues(), true))
            ->count();
        $this->actionNeededCount = $allApps->filter(fn ($a) => $this->isActionRequired($a))->count();
        $this->actionRequiredApp = $allApps->first(fn ($a) => $this->isActionRequired($a));
        $this->quickActions = $this->buildQuickActions($allApps);

        $this->stats = [
            'total' => $this->totalCount,
            'approved' => $this->approvedCount,
            'underReview' => $allApps->filter(fn ($a) => $a->status === ApplicationStatus::UnderReview)->count(),
            'pendingPayment' => $allApps->filter(fn ($a) => $a->status === ApplicationStatus::PaymentPending)->count(),
        ];

        $this->upcomingTrip = $allApps
            ->filter(fn ($a) => $a->status === ApplicationStatus::Approved && $a->travel_date?->isFuture())
            ->sortBy('travel_date')
            ->first();
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
