<x-filament-panels::page>
    @php $summary = $this->getSummary(); @endphp

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-6">
        <div class="rounded-xl bg-white p-4 shadow ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Reviewed (30d)</p>
            <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $summary['reviewed'] }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Approved</p>
            <p class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400">{{ $summary['approved'] }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Rejected</p>
            <p class="mt-1 text-2xl font-semibold text-red-600 dark:text-red-400">{{ $summary['rejected'] }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg Hours / App</p>
            <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">
                {{ $summary['avg_hours'] !== null ? round($summary['avg_hours'], 1).'h' : '—' }}
            </p>
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
