<div>
    <div class="flex items-center justify-between mb-5">
        <h1 class="text-lg font-semibold text-gray-900 dark:text-white">Notifications</h1>
        @if($unreadCount > 0)
            <button wire:click="markAllRead"
                    class="text-sm font-medium transition-colors"
                    style="color:var(--portal-teal)">
                Mark all as read
            </button>
        @endif
    </div>

    @if($notifications->isEmpty())
        <x-empty-state
            icon="ti-bell-off"
            heading="No notifications yet"
            description="You'll see important updates about your applications here." />
    @else
        <x-card>
            <div class="divide-y divide-gray-100 dark:divide-gray-700 -mx-6 -my-4">
                @foreach($notifications as $notification)
                    @php
                        $type    = $notification->data['type'] ?? '';
                        $message = $notification->data['message'] ?? '';
                        $tracking = $notification->data['tracking_number'] ?? null;
                        $isUnread = is_null($notification->read_at);
                        $href = $tracking
                            ? route('applications.wizard', $tracking)
                            : route('dashboard');
                        $dotColor = match ($type) {
                            'application_approved'      => 'var(--portal-teal)',
                            'application_rejected'      => '#c0392b',
                            'document_rejected'         => 'var(--portal-amber)',
                            'payment_succeeded'         => 'var(--portal-teal)',
                            'additional_info_requested' => 'var(--portal-amber)',
                            'appointment_scheduled'     => '#1a5fa8',
                            default                     => '#1a5fa8',
                        };
                    @endphp
                    <a href="{{ $href }}"
                       class="flex items-start gap-3 px-6 py-4 transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50"
                       style="{{ $isUnread ? 'background:var(--portal-teal-soft)' : '' }}">
                        <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full"
                              style="background:{{ $dotColor }};{{ $isUnread ? '' : 'opacity:.35' }}"></span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-0.5">
                                <p class="text-sm text-gray-900 dark:text-white leading-snug">
                                    {{ $message }}
                                </p>
                                @if($isUnread)
                                    <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-white flex-shrink-0"
                                          style="background:var(--portal-teal)">
                                        NEW
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs" style="color:var(--portal-ink-4)">
                                {{ $notification->created_at->diffForHumans() }}
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>
        </x-card>
    @endif
</div>
