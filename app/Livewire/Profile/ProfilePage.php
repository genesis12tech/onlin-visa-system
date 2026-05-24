<?php

namespace App\Livewire\Profile;

use Illuminate\View\View;
use Livewire\Component;

class ProfilePage extends Component
{
    public function render(): View
    {
        return view('livewire.profile.profile-page')
            ->layout('layouts.app', ['title' => 'My Profile']);
    }
}
