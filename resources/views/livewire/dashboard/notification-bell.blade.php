<div wire:poll.30s
     class="relative"
     x-data="{ open: false }"
     @click.outside="open = false">

    {{-- Bell button --}}
    <button @click="open = !open"
            class="relative flex h-9 w-9 items-center justify-center rounded-lg border transition-colors"
            style="background:var(--portal-sand);border-color:var(--portal-sand-3)"
            aria-label="Notifications">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
            <path d="M8 1.5a4.5 4.5 0 014.5 4.5v3l1 2H2.5l1-2V6A4.5 4.5 0 018 1.5z"
                  stroke="currentColor" stroke-width="1.3" style="color:var(--portal-ink-3)"/>
            <path d="M6.5 12.5a1.5 1.5 0 003 0"
                  stroke="currentColor" stroke-width="1.3" style="color:var(--portal-ink-3)"/>
        </svg>
        @if($unreadCount > 0)
            <span class="absolute right-1.5 top-1.5 h-[7px] w-[7px] rounded-full border-2 border-white"
                  style="background:var(--portal-amber)"
                  aria-hidden="true"></span>
        @endif
    </button>

    {{-- Dropdown --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute right-0 mt-2 w-80 rounded-xl border bg-white shadow-lg"
         style="border-color:var(--portal-sand-3);z-index:200;display:none">

        <div class="flex items-center justify-between border-b px-4 py-3"
             style="border-color:var(--portal-sand-3)">
            <span class="text-sm font-semibold" style="color:var(--portal-ink)">Notifications</span>
            @if($unreadCount > 0)
                <button wire:click="markAllAsRead"
                        class="text-xs transition-colors"
                        style="color:var(--portal-teal)">
                    Mark all read
                </button>
            @endif
        </div>

        <ul class="max-h-80 divide-y divide-[var(--portal-sand-3)] overflow-y-auto">
            @forelse($notifications as $notification)
                @php
                    $type     = $notification->data['type'] ?? '';
                    $message  = $notification->data['message'] ?? '';
                    $tracking = $notification->data['tracking_number'] ?? null;
                    $dotColor = match ($type) {
                        'application_approved'      => 'var(--portal-teal)',
                        'application_rejected'      => '#c0392b',
                        'document_rejected'         => 'var(--portal-amber)',
                        'payment_succeeded'         => 'var(--portal-teal)',
                        'additional_info_requested' => 'var(--portal-amber)',
                        'appointment_scheduled'     => '#1a5fa8',
                        default                     => '#1a5fa8',
                    };
                    $href = $tracking
                        ? route('applications.wizard', $tracking)
                        : route('dashboard');
                @endphp
                <li>
                    <a href="{{ $href }}"
                       class="flex gap-3 px-4 py-3 transition-colors hover:bg-gray-50"
                       style="{{ is_null($notification->read_at) ? 'background:var(--portal-teal-soft)' : '' }}">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                              style="background:{{ $dotColor }};{{ $notification->read_at ? 'opacity:.4' : '' }}"></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs leading-relaxed" style="color:var(--portal-ink-2)">
                                {{ $message }}
                            </p>
                            <p class="mt-1 text-[11px]" style="color:var(--portal-ink-4)">
                                {{ $notification->created_at->diffForHumans() }}
                            </p>
                        </div>
                    </a>
                </li>
            @empty
                <li class="flex flex-col items-center justify-center px-4 py-8">
                    <i class="ti ti-bell-off text-2xl" style="color:var(--portal-sand-3)"></i>
                    <p class="mt-2 text-xs" style="color:var(--portal-ink-4)">No notifications yet</p>
                </li>
            @endforelse
        </ul>

    </div>
</div>
