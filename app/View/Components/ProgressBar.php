<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class ProgressBar extends Component
{
    public function __construct(
        public int $percent,
        public string $label,
        public string $colour = 'bg-blue-500',
    ) {}

    public function render(): View
    {
        return view('components.progress-bar');
    }
}
