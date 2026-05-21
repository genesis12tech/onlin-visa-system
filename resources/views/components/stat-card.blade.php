<div {{ $attributes->merge(['class' => 'bg-gray-100 dark:bg-gray-800 rounded-xl p-3.5']) }}>
    <p class="text-2xl font-medium {{ $highlight ? $highlightColour : 'text-gray-900 dark:text-white' }} mb-0.5">
        {{ $value }}
    </p>
    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</p>
</div>
