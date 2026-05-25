<?php

namespace App\Livewire\Notifications;

use Illuminate\View\View;
use Livewire\Component;

class NotificationsPage extends Component
{
    public int $unreadCount = 0;

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
        $this->unreadCount = 0;
    }

    public function render(): View
    {
        $notifications = auth()->user()
            ->notifications()
            ->latest()
            ->get();

        $this->unreadCount = $notifications->whereNull('read_at')->count();

        return view('livewire.notifications.notifications-page', [
            'notifications' => $notifications,
        ])->layout('layouts.app', ['title' => 'Notifications']);
    }
}
