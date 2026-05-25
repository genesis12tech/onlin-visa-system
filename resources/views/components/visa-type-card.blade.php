@props(['type', 'selected' => false])

@php
$border = $selected
    ? 'border-teal-500 bg-teal-50 dark:bg-teal-900/20'
    : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-gray-300 dark:hover:border-gray-600';
@endphp

<div {{ $attributes->merge(['class' => "h-full rounded-xl border-2 p-5 shadow-sm transition-colors cursor-pointer $border"]) }}>
    <div class="flex items-start gap-3">
        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg
            {{ $selected ? 'bg-teal-100 dark:bg-teal-900/40' : 'bg-gray-100 dark:bg-gray-700' }}">
            <i class="ti ti-certificate text-xl {{ $selected ? 'text-teal-600 dark:text-teal-400' : 'text-gray-500 dark:text-gray-400' }}"></i>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $type->name }}</p>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                {{ $type->processing_days }} days processing
            </p>
            @if($type->description)
                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400 line-clamp-2">{{ $type->description }}</p>
            @endif
            <div class="mt-3 flex flex-wrap gap-2">
                <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs text-gray-600 dark:text-gray-300">
                    {{ $type->validity_days }} days validity
                </span>
                @if($type->formattedFee() !== '—')
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                        {{ $selected ? 'bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">
                        {{ $type->formattedFee() }}
                    </span>
                @endif
            </div>
        </div>
        @if($selected)
            <i class="ti ti-circle-check text-xl text-teal-600 dark:text-teal-400 flex-shrink-0 mt-0.5"></i>
        @endif
    </div>
</div>
