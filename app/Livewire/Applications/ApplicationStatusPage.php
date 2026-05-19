<?php

namespace App\Livewire\Applications;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ApplicationStatusPage extends Component
{
    #[Locked]
    public string $applicationUlid;

    public function mount(string $applicationUlid): void
    {
        $this->applicationUlid = $applicationUlid;
    }

    public function render(): View
    {
        $application = VisaApplication::with([
            'visaType',
            'visaType.country',
            'statusHistories' => fn ($q) => $q->orderBy('created_at'),
            'notes' => fn ($q) => $q->where('is_visible_to_applicant', true)->latest(),
            'payments.invoice',
            'latestAppointment',
        ])->where('ulid', $this->applicationUlid)->firstOrFail();

        $latestPayment = $application->payments->sortByDesc('created_at')->first();
        $statusInfo = $this->resolveStatusInfo($application->status);

        return view('livewire.applications.application-status-page', [
            'application' => $application,
            'latestPayment' => $latestPayment,
            'statusInfo' => $statusInfo,
        ]);
    }

    /** @return array{icon: string, bgClass: string, iconClass: string, description: string} */
    private function resolveStatusInfo(ApplicationStatus $status): array
    {
        return match ($status) {
            ApplicationStatus::Submitted => [
                'icon' => 'ti-clock',
                'bgClass' => 'bg-blue-100 dark:bg-blue-900/30',
                'iconClass' => 'text-blue-600 dark:text-blue-400',
                'description' => 'Your application has been received and is awaiting review.',
            ],
            ApplicationStatus::PaymentPending => [
                'icon' => 'ti-credit-card',
                'bgClass' => 'bg-amber-100 dark:bg-amber-900/30',
                'iconClass' => 'text-amber-600 dark:text-amber-400',
                'description' => 'Your application is awaiting payment before processing can begin.',
            ],
            ApplicationStatus::PaymentCompleted => [
                'icon' => 'ti-receipt',
                'bgClass' => 'bg-green-100 dark:bg-green-900/30',
                'iconClass' => 'text-green-600 dark:text-green-400',
                'description' => 'Your payment has been confirmed. Your application is queued for review.',
            ],
            ApplicationStatus::UnderReview => [
                'icon' => 'ti-search',
                'bgClass' => 'bg-amber-100 dark:bg-amber-900/30',
                'iconClass' => 'text-amber-600 dark:text-amber-400',
                'description' => 'An officer is currently reviewing your application. You will be notified of any updates.',
            ],
            ApplicationStatus::DocsRequired => [
                'icon' => 'ti-file-alert',
                'bgClass' => 'bg-amber-100 dark:bg-amber-900/30',
                'iconClass' => 'text-amber-600 dark:text-amber-400',
                'description' => 'Additional documents are required before your application can proceed.',
            ],
            ApplicationStatus::Approved => [
                'icon' => 'ti-circle-check',
                'bgClass' => 'bg-green-100 dark:bg-green-900/30',
                'iconClass' => 'text-green-600 dark:text-green-400',
                'description' => 'Congratulations! Your visa application has been approved.',
            ],
            ApplicationStatus::Rejected => [
                'icon' => 'ti-circle-x',
                'bgClass' => 'bg-red-100 dark:bg-red-900/30',
                'iconClass' => 'text-red-600 dark:text-red-400',
                'description' => 'Your visa application has not been approved.',
            ],
            ApplicationStatus::Withdrawn => [
                'icon' => 'ti-ban',
                'bgClass' => 'bg-gray-100 dark:bg-gray-700',
                'iconClass' => 'text-gray-400 dark:text-gray-500',
                'description' => 'This application was withdrawn.',
            ],
            default => [
                'icon' => 'ti-file',
                'bgClass' => 'bg-gray-100 dark:bg-gray-700',
                'iconClass' => 'text-gray-400 dark:text-gray-500',
                'description' => '',
            ],
        };
    }
}
