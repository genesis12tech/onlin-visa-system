<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class QuickActions extends Component
{
    /**
     * @param  array<int, array{icon: string, label: string, href: string}>  $actions
     */
    public function __construct(
        public array $actions = [],
    ) {}

    public function render(): View
    {
        return view('components.quick-actions');
    }
}
