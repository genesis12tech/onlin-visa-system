<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class Textarea extends Component
{
    public function __construct(
        public string $name,
        public string $label = '',
        public bool $required = false,
        public int $rows = 4,
    ) {}

    public function render(): View
    {
        return view('components.textarea');
    }
}
