@props(['title' => null])

@php
    $pageTitle = $title ?? match (request()->route()?->getName()) {
        'dashboard'                      => 'Dashboard',
        'applications.index'             => 'My Applications',
        'applications.start',
        'applications.wizard'            => 'Application',
        'documents'                      => 'My Documents',
        'payments', 'applications.pay'   => 'Payments',
        'track'                          => 'Track Status',
        'notifications'                  => 'Notifications',
        'profile', 'profile.setup'       => 'My Profile',
        default                          => config('app.name'),
    };

    $user      = auth()->user();
    $nameParts = $user ? explode(' ', trim($user->name)) : ['?'];
    $initials  = mb_strtoupper(mb_substr($nameParts[0], 0, 1) ?: '?');
    if (count($nameParts) > 1) {
        $initials .= mb_strtoupper(mb_substr(end($nameParts), 0, 1));
    }
@endphp

<header class="portal-topbar">
    <span class="text-base font-bold leading-tight" style="color:var(--portal-ink);letter-spacing:-.3px">
        {{ $pageTitle }}
    </span>

    <div class="flex items-center gap-2.5">
        {{-- Notification bell (Livewire) --}}
        <livewire:dashboard.notification-bell />

        {{-- User avatar --}}
        <div class="flex h-9 w-9 cursor-default select-none items-center justify-center rounded-full
                    text-sm font-bold text-white"
             style="background:var(--portal-teal)"
             title="{{ $user?->name }}">
            {{ $initials }}
        </div>
    </div>
</header>
