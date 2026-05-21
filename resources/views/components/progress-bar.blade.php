<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    <div class="flex-1 h-1 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
        <div class="h-1 rounded-full {{ $colour }}" style="width: {{ $percent }}%"></div>
    </div>
    <span class="text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap">{{ $label }}</span>
</div>
