<?php

namespace App\View\Components;

use App\Domain\Applications\Models\VisaApplication;
use Illuminate\View\Component;
use Illuminate\View\View;

class ApplicationCard extends Component
{
    public function __construct(
        public VisaApplication $application,
    ) {}

    public function render(): View
    {
        return view('components.application-card');
    }
}
