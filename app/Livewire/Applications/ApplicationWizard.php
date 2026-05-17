<?php

namespace App\Livewire\Applications;

use Illuminate\View\View;
use Livewire\Component;

class ApplicationWizard extends Component
{
    public string $tracking;

    public function mount(string $tracking): void
    {
        $this->tracking = $tracking;
    }

    public function render(): View
    {
        return view('livewire.applications.application-wizard');
    }
}
