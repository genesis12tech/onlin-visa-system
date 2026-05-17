<?php

namespace App\View\Components;

use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Illuminate\View\View;

class StatusTimeline extends Component
{
    public function __construct(
        public Collection $histories,
        public bool $publicOnly = false,
    ) {}

    public function render(): View
    {
        return view('components.status-timeline');
    }
}
