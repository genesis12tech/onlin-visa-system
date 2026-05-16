<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class Button extends Component
{
    public function __construct(
        public string $variant = 'primary',
        public string $type = 'button',
        public bool $loading = false,
    ) {}

    public function render(): View
    {
        return view('components.button');
    }
}
