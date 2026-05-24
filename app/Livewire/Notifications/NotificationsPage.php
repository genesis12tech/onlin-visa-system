<?php

namespace App\Livewire\Notifications;

use Illuminate\View\View;
use Livewire\Component;

class NotificationsPage extends Component
{
    public function render(): View
    {
        return view('livewire.notifications.notifications-page')
            ->layout('layouts.app', ['title' => 'Notifications']);
    }
}
