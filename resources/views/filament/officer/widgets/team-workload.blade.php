<x-filament-widgets::widget>
    <x-filament::section heading="Team Workload">
        <ul class="space-y-4">
            @foreach ($members as $member)
                <li class="flex items-center gap-3">
                    <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-gray-700 text-xs font-bold text-white">
                        {{ $member['initials'] }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-white">{{ $member['name'] }}</p>
                        <p class="truncate text-xs text-gray-400">{{ $member['specialisations'] }}</p>
                    </div>
                    <div class="w-24">
                        <div class="h-1.5 rounded-full bg-gray-700">
                            <div class="h-1.5 rounded-full {{ $member['bar_color'] ?? 'bg-green-500' }}"
                                 style="width: {{ min(100, ($member['assigned'] / $member['capacity']) * 100) }}%">
                            </div>
                        </div>
                    </div>
                    <span class="text-xs text-gray-400 tabular-nums">
                        {{ $member['assigned'] }}/{{ $member['capacity'] }}
                    </span>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
