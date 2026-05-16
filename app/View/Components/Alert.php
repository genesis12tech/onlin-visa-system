<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class Alert extends Component
{
    public function __construct(
        public string $type = 'info',
        public bool $dismissible = true,
    ) {}

    public function render(): View
    {
        return view('components.alert');
    }
}
