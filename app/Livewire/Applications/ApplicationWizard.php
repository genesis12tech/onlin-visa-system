<?php

namespace App\Livewire\Applications;

use App\Domain\Applications\Actions\SubmitApplication;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class ApplicationWizard extends Component
{
    #[Locked]
    public string $tracking;

    #[Locked]
    public VisaApplication $application;

    public int $currentSectionIndex = 0;

    public bool $onReviewStep = false;

    public bool $submitting = false;

    public function mount(string $tracking): void
    {
        $this->tracking = $tracking;
        $this->application = VisaApplication::where('tracking_number', $tracking)
            ->with(['formTemplate', 'visaType', 'answers'])
            ->firstOrFail();

        Gate::authorize('view', $this->application);
    }

    /** @return array<int, array<string, mixed>> */
    public function sections(): array
    {
        return $this->application->formTemplate->schema['sections'] ?? [];
    }

    /** @return array<string, mixed> */
    public function currentSection(): array
    {
        return $this->sections()[$this->currentSectionIndex] ?? [];
    }

    /** @return array<string, mixed> keyed by bare field_key */
    public function savedAnswersForSection(string $sectionKey): array
    {
        return $this->application->answers
            ->filter(fn ($a) => str_starts_with($a->field_key, "{$sectionKey}."))
            ->keyBy(fn ($a) => str_after($a->field_key, "{$sectionKey}."))
            ->map(fn ($a) => $a->value)
            ->toArray();
    }

    public function advance(): void
    {
        Gate::authorize('update', $this->application);

        $lastIndex = count($this->sections()) - 1;

        if ($this->currentSectionIndex < $lastIndex) {
            $this->currentSectionIndex++;
        } else {
            $this->onReviewStep = true;
        }
    }

    public function goBack(): void
    {
        if ($this->onReviewStep) {
            $this->onReviewStep = false;
        } elseif ($this->currentSectionIndex > 0) {
            $this->currentSectionIndex--;
        }
    }

    public function canSubmit(): bool
    {
        if ($this->application->status !== ApplicationStatus::Draft) {
            return false;
        }

        return ! $this->application->documents()
            ->whereIn('status', ['pending', 'rejected', 'infected'])
            ->exists();
    }

    public function submit(): void
    {
        Gate::authorize('submit', $this->application);

        $this->submitting = true;

        app(SubmitApplication::class)->execute($this->application, auth()->user());

        $this->redirect(route('applications.wizard', $this->tracking));
    }

    #[On('section-updated')]
    public function sectionSaved(string $sectionKey): void
    {
        $this->application->load('answers');
    }

    public function render(): View
    {
        return view('livewire.applications.application-wizard')
            ->layout('layouts.app', ['title' => 'Application — '.$this->application->tracking_number]);
    }
}
