<x-guest-layout>
    <div class="w-full max-w-md text-center">
        <div class="mb-6 flex justify-center">
            <span class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30">
                <i class="ti ti-clock-hour-4 text-3xl text-amber-600 dark:text-amber-400" aria-hidden="true"></i>
            </span>
        </div>

        <h1 class="mb-2 text-2xl font-bold text-gray-900 dark:text-white">Too many requests</h1>

        <p class="mb-6 text-gray-600 dark:text-gray-400">
            You have made too many requests in a short period of time.
            Please wait a moment before trying again.
        </p>

        @php
            $retryAfter = request()->header('Retry-After');
        @endphp

        @if($retryAfter)
            <p class="mb-6 text-sm text-gray-500 dark:text-gray-500">
                Try again in {{ $retryAfter }} {{ Str::plural('second', (int) $retryAfter) }}.
            </p>
        @endif

        <div class="flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
            <x-button
                tag="a"
                href="{{ url()->previous('/') }}"
                variant="primary"
            >
                <i class="ti ti-arrow-left text-sm" aria-hidden="true"></i>
                Go back
            </x-button>

            <x-button
                tag="a"
                href="{{ route('dashboard') }}"
                variant="secondary"
            >
                Dashboard
            </x-button>
        </div>
    </div>
</x-guest-layout>
