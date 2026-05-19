@php $breachCount = $this->getBreachCount(); @endphp

<div>
    @if ($breachCount > 0)
        <div class="rounded-xl bg-red-50 border border-red-200 p-4 dark:bg-red-950/20 dark:border-red-800">
            <div class="flex items-center gap-3">
                <x-filament::icon
                    icon="heroicon-o-exclamation-triangle"
                    class="h-5 w-5 text-red-600 dark:text-red-400 shrink-0"
                />
                <p class="text-sm font-medium text-red-800 dark:text-red-300">
                    <strong>{{ $breachCount }} {{ Str::plural('application', $breachCount) }}</strong>
                    {{ $breachCount === 1 ? 'has' : 'have' }} exceeded the SLA deadline and
                    {{ $breachCount === 1 ? 'requires' : 'require' }} immediate attention.
                </p>
                <a
                    href="{{ route('filament.officer.pages.sla-breaches') }}"
                    class="ml-auto shrink-0 text-sm font-medium text-red-700 underline hover:text-red-900 dark:text-red-400 dark:hover:text-red-200"
                >
                    View all
                </a>
            </div>
        </div>
    @endif
</div>
