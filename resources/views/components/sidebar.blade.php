@php
    $user = auth()->user();

    // Avatar initials: first letter of first + last word in name
    $nameParts = $user ? explode(' ', trim($user->name)) : ['?'];
    $initials  = mb_strtoupper(mb_substr($nameParts[0], 0, 1) ?: '?');
    if (count($nameParts) > 1) {
        $initials .= mb_strtoupper(mb_substr(end($nameParts), 0, 1));
    }

    // Unread notification count for the sidebar badge
    $unreadNotifications = $user ? once(fn () => $user->unreadNotifications()->count()) : 0;
@endphp

<aside class="portal-sidebar">

    {{-- Logo --}}
    <div class="flex items-center gap-2.5 border-b px-5 py-6" style="border-color:var(--portal-sand-3)">
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-lg"
             style="background:var(--portal-teal)">
            🌐
        </div>
        <div class="sb-logo-text">
            <div class="text-sm font-bold leading-tight" style="color:var(--portal-ink);letter-spacing:-.3px">
                VisaPortal
            </div>
            <div class="text-[10px]" style="color:var(--portal-ink-4)">Applicant Portal</div>
        </div>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 overflow-y-auto px-3 py-3.5">

        <div class="sb-section-label px-2 pb-1 pt-2.5 text-[9px] font-bold uppercase tracking-widest"
             style="color:var(--portal-ink-4)">
            Menu
        </div>

        {{-- Dashboard --}}
        <a href="{{ route('dashboard') }}"
           class="sb-item {{ request()->routeIs('dashboard') ? 'sb-active' : '' }}">
            <svg class="h-4 w-4 shrink-0 opacity-80" viewBox="0 0 16 16" fill="none">
                <rect x="1" y="1" width="6" height="6" rx="1.5" fill="currentColor" opacity=".8"/>
                <rect x="9" y="1" width="6" height="6" rx="1.5" fill="currentColor" opacity=".5"/>
                <rect x="1" y="9" width="6" height="6" rx="1.5" fill="currentColor" opacity=".5"/>
                <rect x="9" y="9" width="6" height="6" rx="1.5" fill="currentColor" opacity=".3"/>
            </svg>
            <span class="sb-label">Dashboard</span>
        </a>

        {{-- My Applications --}}
        <a href="{{ route('applications.index') }}"
           class="sb-item {{ request()->routeIs('applications.index') ? 'sb-active' : '' }}">
            <svg class="h-4 w-4 shrink-0 opacity-80" viewBox="0 0 16 16" fill="none">
                <path d="M3 2h10a1 1 0 011 1v10a1 1 0 01-1 1H3a1 1 0 01-1-1V3a1 1 0 011-1z"
                      stroke="currentColor" stroke-width="1.3"/>
                <path d="M5 6h6M5 8.5h4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
            </svg>
            <span class="sb-label">My Applications</span>
        </a>

        {{-- New Application --}}
        <a href="{{ route('applications.start') }}"
           class="sb-item {{ request()->routeIs('applications.start', 'applications.wizard') ? 'sb-active' : '' }}">
            <svg class="h-4 w-4 shrink-0 opacity-80" viewBox="0 0 16 16" fill="none">
                <circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.3"/>
                <path d="M8 5v6M5 8h6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
            </svg>
            <span class="sb-label">New Application</span>
        </a>

        {{-- Documents --}}
        <a href="{{ route('documents') }}"
           class="sb-item {{ request()->routeIs('documents') ? 'sb-active' : '' }}">
            <svg class="h-4 w-4 shrink-0 opacity-80" viewBox="0 0 16 16" fill="none">
                <path d="M4 1h5l4 4v9a1 1 0 01-1 1H4a1 1 0 01-1-1V2a1 1 0 011-1z"
                      stroke="currentColor" stroke-width="1.3"/>
                <path d="M9 1v4h4" stroke="currentColor" stroke-width="1.3"/>
            </svg>
            <span class="sb-label">Documents</span>
        </a>

        {{-- Payments --}}
        <a href="{{ route('payments') }}"
           class="sb-item {{ request()->routeIs('payments', 'applications.pay') ? 'sb-active' : '' }}">
            <svg class="h-4 w-4 shrink-0 opacity-80" viewBox="0 0 16 16" fill="none">
                <rect x="1" y="4" width="14" height="10" rx="1.5" stroke="currentColor" stroke-width="1.3"/>
                <path d="M1 7h14" stroke="currentColor" stroke-width="1.3"/>
            </svg>
            <span class="sb-label">Payments</span>
        </a>

        {{-- Track Status --}}
        <a href="{{ route('track') }}"
           class="sb-item {{ request()->routeIs('track') ? 'sb-active' : '' }}">
            <svg class="h-4 w-4 shrink-0 opacity-80" viewBox="0 0 16 16" fill="none">
                <circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.3"/>
                <path d="M8 4.5v4l2.5 2" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
            </svg>
            <span class="sb-label">Track Status</span>
        </a>

        <div class="sb-section-label px-2 pb-1 pt-3 text-[9px] font-bold uppercase tracking-widest"
             style="color:var(--portal-ink-4)">
            Account
        </div>

        {{-- Notifications --}}
        <a href="{{ route('notifications') }}"
           class="sb-item {{ request()->routeIs('notifications') ? 'sb-active' : '' }}">
            <svg class="h-4 w-4 shrink-0 opacity-80" viewBox="0 0 16 16" fill="none">
                <path d="M8 1.5a4.5 4.5 0 014.5 4.5v3l1 2H2.5l1-2V6A4.5 4.5 0 018 1.5z"
                      stroke="currentColor" stroke-width="1.3"/>
                <path d="M6.5 12.5a1.5 1.5 0 003 0" stroke="currentColor" stroke-width="1.3"/>
            </svg>
            <span class="sb-label">Notifications</span>
            @if($unreadNotifications > 0)
                <span class="sb-badge ml-auto rounded-full px-1.5 py-0.5 text-[9px] font-bold text-white"
                      style="background:var(--portal-teal)">
                    {{ $unreadNotifications > 9 ? '9+' : $unreadNotifications }}
                </span>
            @endif
        </a>

        {{-- My Profile --}}
        <a href="{{ route('profile') }}"
           class="sb-item {{ request()->routeIs('profile') ? 'sb-active' : '' }}">
            <svg class="h-4 w-4 shrink-0 opacity-80" viewBox="0 0 16 16" fill="none">
                <circle cx="8" cy="5.5" r="3" stroke="currentColor" stroke-width="1.3"/>
                <path d="M2 14c0-3 2.686-4.5 6-4.5s6 1.5 6 4.5"
                      stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
            </svg>
            <span class="sb-label">My Profile</span>
        </a>

    </nav>

    {{-- Footer: avatar + name + email + sign out --}}
    <div class="border-t p-3.5" style="border-color:var(--portal-sand-3)">
        <div class="flex items-center gap-2.5 rounded-lg p-2.5" style="background:var(--portal-sand)">
            <div class="flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-full
                        text-[13px] font-bold text-white"
                 style="background:var(--portal-teal)">
                {{ $initials }}
            </div>
            <div class="sb-user-info min-w-0 flex-1">
                <div class="truncate text-[13px] font-semibold" style="color:var(--portal-ink)">
                    {{ $user?->name }}
                </div>
                <div class="truncate text-[11px]" style="color:var(--portal-ink-4)">
                    {{ $user?->email }}
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="sb-user-info shrink-0">
                @csrf
                <button type="submit"
                        class="transition-colors hover:text-red-600"
                        style="color:var(--portal-ink-4)"
                        title="Sign out">
                    <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none">
                        <path d="M6 14H3a1 1 0 01-1-1V3a1 1 0 011-1h3M11 11l3-3-3-3M14 8H6"
                              stroke="currentColor" stroke-width="1.3"
                              stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>

</aside>
