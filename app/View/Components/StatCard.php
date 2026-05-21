<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class StatCard extends Component
{
    public function __construct(
        public string|int $value,
        public string $label,
        public bool $highlight = false,
        public string $highlightColour = 'text-gray-900 dark:text-white',
    ) {}

    public function render(): View
    {
        return view('components.stat-card');
    }
}
