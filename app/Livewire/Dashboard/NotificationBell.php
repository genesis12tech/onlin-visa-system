<?php

namespace App\Livewire\Dashboard;

use Illuminate\View\View;
use Livewire\Component;

class NotificationBell extends Component
{
    public int $unreadCount = 0;

    public function mount(): void
    {
        $this->unreadCount = auth()->user()->unreadNotifications()->count();
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
        $this->unreadCount = 0;
    }

    public function render(): View
    {
        return view('livewire.dashboard.notification-bell', [
            'notifications' => auth()->user()->notifications()->latest()->limit(10)->get(),
        ]);
    }
}
