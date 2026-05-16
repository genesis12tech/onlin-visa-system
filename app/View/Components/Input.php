<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class Input extends Component
{
    public function __construct(
        public string $name,
        public string $label = '',
        public bool $required = false,
        public string $hint = '',
        public string $type = 'text',
    ) {}

    public function render(): View
    {
        return view('components.input');
    }
}
