<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class Select extends Component
{
    public function __construct(
        public string $name,
        public string $label = '',
        public bool $required = false,
    ) {}

    public function render(): View
    {
        return view('components.select');
    }
}
