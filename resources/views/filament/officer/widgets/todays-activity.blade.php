<x-filament-widgets::widget>
    <x-filament::section heading="Today's Activity">
        <ul class="space-y-4">
            @foreach ($activities as $event)
                <li class="flex items-start gap-3">
                    <div class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full
                        {{ match($event['type'] ?? 'default') {
                            'approved' => 'bg-green-500/20',
                            'requested' => 'bg-yellow-500/20',
                            default => 'bg-gray-700',
                        } }}">
                        @if (($event['type'] ?? '') === 'approved')
                            <x-heroicon-s-check class="h-3 w-3 text-green-400" />
                        @elseif (($event['type'] ?? '') === 'requested')
                            <x-heroicon-s-exclamation-triangle class="h-3 w-3 text-yellow-400" />
                        @else
                            <x-heroicon-s-arrow-up-right class="h-3 w-3 text-gray-400" />
                        @endif
                    </div>
                    <div>
                        <p class="text-sm text-white">{{ $event['description'] }}</p>
                        <p class="text-xs text-gray-500">{{ $event['ago'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
