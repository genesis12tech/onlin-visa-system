<?php

namespace App\Livewire;

use App\Domain\Applications\Actions\WithdrawApplication;
use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class ApplicationDetail extends Component
{
    public bool $isOpen = false;

    public ?string $applicationId = null;

    #[Locked]
    public ?VisaApplication $application = null;

    #[On('openDetail')]
    public function open(string $applicationId): void
    {
        $application = VisaApplication::with([
            'visaType',
            'visaType.country',
            'documents.documentType',
            'documents.currentVersion',
            'statusHistories' => fn ($q) => $q->orderByDesc('created_at'),
        ])->find($applicationId);

        if (! $application || ! Gate::check('view', $application)) {
            return;
        }

        $this->applicationId = $applicationId;
        $this->application = $application;
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->applicationId = null;
        $this->application = null;
    }

    public function cancelApplication(): void
    {
        if (! $this->application || ! Gate::check('withdraw', $this->application)) {
            return;
        }

        WithdrawApplication::run($this->application, auth()->user());
        $this->close();
    }

    public function render(): View
    {
        return view('livewire.application-detail');
    }
}
