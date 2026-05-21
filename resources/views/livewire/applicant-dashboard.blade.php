<div>
    {{-- Alert bar: action required --}}
    @if($actionRequiredApp)
        <x-alert-bar :application="$actionRequiredApp" class="mb-5" />
    @endif

    {{-- Page header --}}
    <div class="flex items-center justify-between mb-5">
        <h1 class="text-lg font-semibold text-gray-900 dark:text-white">
            My applications
        </h1>
        <a href="{{ route('applications.start') }}"
           class="inline-flex items-center gap-1.5 text-sm px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
            <i class="ti ti-plus text-sm" aria-hidden="true"></i>
            New application
        </a>
    </div>

    {{-- Stats row --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <x-stat-card :value="$totalCount" label="Total" />
        <x-stat-card :value="$inProgressCount" label="In progress" />
        <x-stat-card
            :value="$actionNeededCount"
            label="Action needed"
            :highlight="$actionNeededCount > 0"
            highlight-colour="text-amber-600 dark:text-amber-400" />
        <x-stat-card
            :value="$approvedCount"
            label="Approved"
            :highlight="$approvedCount > 0"
            highlight-colour="text-green-600 dark:text-green-400" />
    </div>

    {{-- Two column layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-5">

        {{-- Applications list --}}
        <div>
            <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-3">
                Applications
            </p>

            @forelse($applications as $application)
                <x-application-card :application="$application" class="mb-2.5" />
            @empty
                <x-empty-state
                    icon="ti-file-certificate"
                    heading="No applications yet"
                    description="Start a new application to apply for a visa."
                >
                    <x-slot name="cta">
                        <a href="{{ route('applications.start') }}"
                           class="inline-flex items-center gap-1.5 text-sm px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">
                            <i class="ti ti-plus" aria-hidden="true"></i>
                            New application
                        </a>
                    </x-slot>
                </x-empty-state>
            @endforelse
        </div>

        {{-- Right sidebar --}}
        <div>
            {{-- Notifications --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl mb-4">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                    <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-widest">
                        Recent notifications
                    </p>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse(auth()->user()->notifications()->latest()->take(4)->get() as $notification)
                        @php
                            $dotClass = match ($notification->data['type'] ?? '') {
                                'application_rejected', 'document_rejected' => 'bg-red-500',
                                'application_approved' => 'bg-green-500',
                                'payment_succeeded' => 'bg-blue-500',
                                'additional_info_requested' => 'bg-amber-500',
                                'appointment_scheduled' => 'bg-purple-500',
                                default => $notification->read_at ? 'bg-gray-300 dark:bg-gray-600' : 'bg-blue-500',
                            };
                        @endphp
                        <div class="flex items-start gap-2.5 px-4 py-3">
                            <div class="w-1.5 h-1.5 rounded-full mt-1.5 flex-shrink-0 {{ $dotClass }}">
                            </div>
                            <div>
                                <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">
                                    {{ $notification->data['message'] ?? '' }}
                                </p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                    {{ $notification->created_at->diffForHumans() }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-6 text-center text-xs text-gray-400 dark:text-gray-500">
                            No notifications yet
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Quick actions --}}
            <div>
                <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">
                    Quick actions
                </p>
                <x-quick-actions :actions="$quickActions" />
            </div>
        </div>

    </div>
</div>
