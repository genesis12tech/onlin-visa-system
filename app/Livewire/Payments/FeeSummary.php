<?php

namespace App\Livewire\Payments;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Payments\Actions\CalculateApplicationFee;
use App\Domain\Payments\Actions\CreateCheckoutSession;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class FeeSummary extends Component
{
    #[Locked]
    public string $tracking;

    #[Locked]
    public VisaApplication $application;

    public bool $priorityEnabled = false;

    public ?string $error = null;

    public function mount(string $tracking): void
    {
        $this->tracking = $tracking;
        $this->application = VisaApplication::where('tracking_number', $tracking)
            ->with(['visaType', 'applicantProfile'])
            ->firstOrFail();

        Gate::authorize('view', $this->application);

        if ($this->application->status !== ApplicationStatus::Submitted) {
            $this->redirect(route('applications.wizard', $tracking));
        }
    }

    public function initiatePayment(): void
    {
        Gate::authorize('view', $this->application);

        try {
            $url = (new CreateCheckoutSession)->execute(
                $this->application,
                auth()->user(),
                $this->priorityEnabled,
            );

            $this->redirect($url);
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render(): View
    {
        $feeData = (new CalculateApplicationFee)->execute($this->application, $this->priorityEnabled);

        return view('livewire.payments.fee-summary', [
            'feeData' => $feeData,
        ])->layout('layouts.app', ['title' => 'Pay for your visa application']);
    }
}
