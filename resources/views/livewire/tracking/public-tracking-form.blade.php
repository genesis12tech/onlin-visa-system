<div class="w-full max-w-md mx-auto space-y-6">

    {{-- Header --}}
    <div class="text-center">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Track your application</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Enter your tracking number and email address to see the current status.
        </p>
    </div>

    {{-- Tracking form --}}
    <x-card>
        <form wire:submit="submit" class="space-y-4">
            <div>
                <label for="trackingNumber" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Tracking number
                </label>
                <input
                    id="trackingNumber"
                    type="text"
                    wire:model="trackingNumber"
                    placeholder="e.g. VA-2026-ABCDEF"
                    class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    required
                >
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Email address
                </label>
                <input
                    id="email"
                    type="email"
                    wire:model="email"
                    placeholder="The email used when applying"
                    class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    required
                >
            </div>

            <x-button type="submit" class="w-full" wire:loading.attr="disabled">
                <span wire:loading.remove>Search</span>
                <span wire:loading>Searching…</span>
            </x-button>
        </form>
    </x-card>

    {{-- Not found / error message --}}
    @if($notFound)
        <x-alert type="error" :dismissible="false">
            We could not find an application matching that tracking number and email. Please check your details and try again.
        </x-alert>
    @endif

    {{-- Result --}}
    @if($result)
        @php
            $stages = ['Received', 'Under Review', 'Decision', 'Complete'];
            $activeStep = $result['active_step'];
            $histories = collect($result['histories'])->map(fn ($h) => (object)[
                'public_label' => $h['public_label'],
                'created_at' => \Carbon\Carbon::parse($h['created_at']),
            ]);
        @endphp

        {{-- 4-stage stepper --}}
        <x-card>
            <x-step-indicator :steps="$stages" :current="$activeStep" color="teal" />
        </x-card>

        {{-- Status card --}}
        <x-card>
            <div class="flex items-start justify-between mb-4">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-0.5">
                        {{ $result['visa_type_name'] }}
                    </p>
                    <p class="text-base font-semibold text-gray-900 dark:text-white font-mono">
                        {{ $result['tracking_number'] }}
                    </p>
                </div>
                <x-badge :status="$result['status']" />
            </div>

            <div class="border-t border-gray-100 dark:border-gray-700 pt-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-3">
                    Status history
                </p>
                <x-status-timeline :histories="$histories" :public-only="true" />
            </div>
        </x-card>
    @endif

</div>
