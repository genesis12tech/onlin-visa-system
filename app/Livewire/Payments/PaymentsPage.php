<?php

namespace App\Livewire\Payments;

use Illuminate\View\View;
use Livewire\Component;

class PaymentsPage extends Component
{
    public function render(): View
    {
        return view('livewire.payments.payments-page')
            ->layout('layouts.app', ['title' => 'Payments']);
    }
}
