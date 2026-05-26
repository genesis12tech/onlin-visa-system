<x-filament-widgets::widget>
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-white">
                Good morning, {{ $officerName }} 👋
            </h1>
            <p class="mt-1 text-sm text-gray-400">
                {{ $todayLabel }} · You have {{ $pendingCount }} applications awaiting review
            </p>
        </div>
        <a href="{{ route('filament.officer.pages.review-queue') }}"
           class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 transition">
            Open Review Queue →
        </a>
    </div>
</x-filament-widgets::widget>
