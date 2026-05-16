@props(['type' => 'info', 'dismissible' => true])

@php
[$bg, $border, $text, $icon] = match($type) {
    'success' => ['bg-green-50 dark:bg-green-900/20',  'border-green-200 dark:border-green-800', 'text-green-800 dark:text-green-200', 'ti-circle-check'],
    'error'   => ['bg-red-50 dark:bg-red-900/20',    'border-red-200 dark:border-red-800',   'text-red-800 dark:text-red-200',   'ti-circle-x'],
    'warning' => ['bg-amber-50 dark:bg-amber-900/20', 'border-amber-200 dark:border-amber-800','text-amber-800 dark:text-amber-200','ti-alert-triangle'],
    default   => ['bg-blue-50 dark:bg-blue-900/20',   'border-blue-200 dark:border-blue-800', 'text-blue-800 dark:text-blue-200', 'ti-info-circle'],
};
@endphp

<div
    x-data="{ open: true }"
    x-show="open"
    class="flex items-start gap-3 rounded-lg border p-4 {{ $bg }} {{ $border }} {{ $text }}"
    role="alert"
>
    <i class="ti {{ $icon }} text-lg flex-shrink-0 mt-0.5"></i>
    <div class="flex-1 text-sm">{{ $slot }}</div>
    @if($dismissible)
        <button @click="open = false" class="flex-shrink-0 hover:opacity-75" aria-label="Dismiss">
            <i class="ti ti-x text-base"></i>
        </button>
    @endif
</div>
