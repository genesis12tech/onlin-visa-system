<?php

namespace App\Livewire;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

class ApplicationList extends Component
{
    public string $filter = 'all';

    public Collection $applications;

    public function mount(string $filter = 'all'): void
    {
        $this->filter = $filter;
        $this->load();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->load();
    }

    public function load(): void
    {
        $profile = auth()->user()->applicantProfile;

        if (! $profile) {
            $this->applications = collect();

            return;
        }

        $query = VisaApplication::where('applicant_profile_id', $profile->ulid)
            ->with([
                'visaType',
                'visaType.country',
                'documents' => fn ($q) => $q->with('documentType'),
            ])
            ->orderByDesc('updated_at');

        $statuses = $this->filterStatuses();
        if ($statuses !== null) {
            $query->whereIn('status', $statuses);
        }

        $this->applications = $query->get();
    }

    /** @return string[]|null */
    private function filterStatuses(): ?array
    {
        return match ($this->filter) {
            'approved' => [ApplicationStatus::Approved->value],
            'in_review' => [
                ApplicationStatus::UnderReview->value,
                ApplicationStatus::AdditionalInfoRequested->value,
                ApplicationStatus::DocsRequired->value,
                ApplicationStatus::PaymentCompleted->value,
            ],
            'submitted' => [
                ApplicationStatus::Submitted->value,
                ApplicationStatus::PaymentPending->value,
            ],
            'rejected' => [ApplicationStatus::Rejected->value],
            default => null,
        };
    }

    public function render(): View
    {
        return view('livewire.application-list')
            ->layout('layouts.app');
    }
}
