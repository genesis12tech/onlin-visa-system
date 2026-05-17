<x-app-layout title="My Dashboard">
    <div class="space-y-8">

        {{-- Page header --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    Welcome, {{ $profile?->first_name ?? auth()->user()->name }}
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage your visa applications from here.</p>
            </div>
            <x-button tag="a" href="{{ route('applications.start') }}">
                <i class="ti ti-plus text-base"></i>
                New application
            </x-button>
        </div>

        {{-- Application list --}}
        @if($applications->isEmpty())
            <x-empty-state
                icon="ti-file-certificate"
                heading="No applications yet"
                description="Start a new application to apply for a visa. You can save your progress and come back any time."
            >
                <x-slot name="cta">
                    <x-button tag="a" href="{{ route('applications.start') }}">
                        Start your first application
                    </x-button>
                </x-slot>
            </x-empty-state>
        @else
            <div class="space-y-3">
                @foreach($applications as $application)
                    <a href="{{ route('applications.wizard', $application->tracking_number) }}"
                       class="block rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm hover:border-gray-300 dark:hover:border-gray-600 transition-colors p-5">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-4 min-w-0">
                                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/40">
                                    <i class="ti ti-certificate text-xl text-blue-600 dark:text-blue-400"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                        {{ $application->visaType->name }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ $application->visaType->country->name }}
                                        &middot;
                                        <span class="font-mono">{{ $application->tracking_number }}</span>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 flex-shrink-0">
                                <div class="text-right hidden sm:block">
                                    <p class="text-xs text-gray-400 dark:text-gray-500">
                                        @if($application->submitted_at)
                                            Submitted {{ $application->submitted_at->diffForHumans() }}
                                        @else
                                            Started {{ $application->created_at->diffForHumans() }}
                                        @endif
                                    </p>
                                </div>
                                <x-badge :status="$application->status" />
                                <i class="ti ti-chevron-right text-gray-400 dark:text-gray-500"></i>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

    </div>
</x-app-layout>
