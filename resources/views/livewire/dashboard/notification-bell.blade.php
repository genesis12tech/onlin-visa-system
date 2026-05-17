<div class="relative" x-data="{ open: false }" @click.outside="open = false">
    {{-- Bell button --}}
    <button
        @click="open = !open"
        class="relative flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-400 transition-colors"
        aria-label="Notifications"
    >
        <i class="ti ti-bell text-lg"></i>
        @if($unreadCount > 0)
            <span class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white leading-none">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    {{-- Dropdown panel --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute right-0 mt-2 w-80 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg z-50"
        style="display: none;"
    >
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700">
            <span class="text-sm font-semibold text-gray-900 dark:text-white">Notifications</span>
            @if($unreadCount > 0)
                <button
                    wire:click="markAllAsRead"
                    class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors"
                >
                    Mark all read
                </button>
            @endif
        </div>

        <ul class="max-h-80 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700">
            @forelse($notifications as $notification)
                @php
                    $type     = $notification->data['type'] ?? '';
                    $message  = $notification->data['message'] ?? '';
                    $tracking = $notification->data['tracking_number'] ?? null;
                    $iconClass = match ($type) {
                        'application_approved'       => 'ti-circle-check text-green-500',
                        'application_rejected'       => 'ti-circle-x text-red-500',
                        'document_rejected'          => 'ti-file-x text-amber-500',
                        'payment_succeeded'          => 'ti-receipt text-green-500',
                        'appointment_scheduled'      => 'ti-calendar-event text-blue-500',
                        'additional_info_requested'  => 'ti-info-circle text-amber-500',
                        default                      => 'ti-bell text-blue-500',
                    };
                    $href = $tracking
                        ? route('applications.wizard', $tracking)
                        : route('dashboard');
                @endphp
                <li>
                    <a
                        href="{{ $href }}"
                        class="flex gap-3 px-4 py-3 {{ is_null($notification->read_at) ? 'bg-blue-50 dark:bg-blue-900/10' : '' }} hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                    >
                        <span class="flex-shrink-0 mt-0.5 flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700">
                            <i class="ti {{ $iconClass }} text-sm"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">
                                {{ $message }}
                            </p>
                            <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                                {{ $notification->created_at->diffForHumans() }}
                            </p>
                        </div>
                    </a>
                </li>
            @empty
                <li class="flex flex-col items-center justify-center px-4 py-8">
                    <i class="ti ti-bell-off text-2xl text-gray-300 dark:text-gray-600"></i>
                    <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">No notifications yet</p>
                </li>
            @endforelse
        </ul>
    </div>
</div>
