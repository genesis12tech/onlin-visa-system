<?php

namespace App\View\Components;

use App\Domain\Applications\Enums\ApplicationStatus;
use Illuminate\View\Component;
use Illuminate\View\View;

class StatusBadge extends Component
{
    public string $badgeLabel;

    public string $badgeColour;

    public function __construct(ApplicationStatus|string $status)
    {
        $enum = $status instanceof ApplicationStatus
            ? $status
            : ApplicationStatus::tryFrom($status);

        $this->badgeLabel = $enum?->publicLabel() ?? ucfirst(str_replace('_', ' ', is_string($status) ? $status : $status->value));
        $this->badgeColour = match ($enum?->colour()) {
            'green' => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300',
            'red' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
            'amber' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
            'blue' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
            'purple' => 'bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
            default => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
        };
    }

    public function render(): View
    {
        return view('components.status-badge');
    }
}
