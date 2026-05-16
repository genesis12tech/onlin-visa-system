<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class EmptyState extends Component
{
    public function __construct(
        public string $icon = 'ti-folder-open',
        public string $heading = 'Nothing here yet',
        public string $description = '',
    ) {}

    public function render(): View
    {
        return view('components.empty-state');
    }
}
