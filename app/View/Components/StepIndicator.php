<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class StepIndicator extends Component
{
    public function __construct(
        /** @var array<int, string> $steps */
        public array $steps,
        public int $current,
    ) {}

    public function render(): View
    {
        return view('components.step-indicator');
    }
}
