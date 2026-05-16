<?php

namespace App\View\Components;

use App\Domain\Applications\Enums\ApplicationStatus;
use Illuminate\View\Component;
use Illuminate\View\View;

class Badge extends Component
{
    public string $colorClasses;

    public function __construct(
        public ?ApplicationStatus $status = null,
        public string $color = 'gray',
    ) {
        $resolvedColor = $status ? $this->statusColor($status) : $color;

        $this->colorClasses = match ($resolvedColor) {
            'green' => 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200',
            'blue' => 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200',
            'purple' => 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-200',
            'amber' => 'bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200',
            'red' => 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200',
            default => 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300',
        };
    }

    private function statusColor(ApplicationStatus $status): string
    {
        return match ($status) {
            ApplicationStatus::Draft, ApplicationStatus::Withdrawn => 'gray',
            ApplicationStatus::Submitted, ApplicationStatus::PaymentPending => 'blue',
            ApplicationStatus::PaymentCompleted, ApplicationStatus::UnderReview,
            ApplicationStatus::DocsRequired => 'purple',
            ApplicationStatus::AdditionalInfoRequested => 'amber',
            ApplicationStatus::Approved => 'green',
            ApplicationStatus::Rejected => 'red',
        };
    }

    public function render(): View
    {
        return view('components.badge');
    }
}
