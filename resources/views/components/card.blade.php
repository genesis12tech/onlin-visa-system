@props(['title' => ''])

<div {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm']) }}>
    @if($title)
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $title }}</h3>
        </div>
    @endif
    <div class="px-6 py-4">
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="px-6 py-3 bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-700 rounded-b-xl">
            {{ $footer }}
        </div>
    @endisset
</div>
