<x-guest-layout>
    <div class="flex min-h-[calc(100vh-4rem)] items-center justify-center px-4 py-12">
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
                <a
                    href="{{ url()->previous('/') }}"
                    class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-400"
                >
                    <i class="ti ti-arrow-left text-sm" aria-hidden="true"></i>
                    Go back
                </a>

                <a
                    href="{{ route('dashboard') }}"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                >
                    Dashboard
                </a>
            </div>
        </div>
    </div>
</x-guest-layout>
