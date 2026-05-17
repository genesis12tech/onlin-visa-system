<?php

namespace App\Livewire\Payments;

use Illuminate\View\View;
use Livewire\Component;

class FeeSummary extends Component
{
    public function render(): View
    {
        return view('livewire.payments.fee-summary');
    }
}
