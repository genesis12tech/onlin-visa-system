<x-filament-panels::page>
    {{-- Full-width header --}}
    @livewire(\App\Filament\Officer\Widgets\DashboardHeader::class)

    {{-- Stat cards — full width --}}
    @livewire(\App\Filament\Officer\Widgets\StatsOverview::class)

    {{-- Two-column area: Priority Queue (left) + right panel (Team + Activity) --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        <div class="xl:col-span-2">
            @livewire(\App\Filament\Officer\Widgets\PriorityQueueTable::class)
        </div>

        <div class="flex flex-col gap-6">
            @livewire(\App\Filament\Officer\Widgets\TeamWorkload::class)
            @livewire(\App\Filament\Officer\Widgets\TodaysActivity::class)
        </div>

    </div>
</x-filament-panels::page>
