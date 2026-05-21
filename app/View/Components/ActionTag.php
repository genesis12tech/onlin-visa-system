<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class ActionTag extends Component
{
    public function __construct(
        public string $message = '',
    ) {}

    public function render(): View
    {
        return view('components.action-tag');
    }
}
