<?php

namespace App\Livewire\Tracking;

use Illuminate\View\View;
use Livewire\Component;

class PublicTrackingForm extends Component
{
    public function render(): View
    {
        return view('livewire.tracking.public-tracking-form');
    }
}
