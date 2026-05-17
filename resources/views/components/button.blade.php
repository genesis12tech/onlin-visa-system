@props(['variant' => 'primary', 'type' => 'button', 'loading' => false, 'tag' => 'button'])

@php
$classes = match($variant) {
    'primary'   => 'bg-blue-600 hover:bg-blue-700 text-white focus:ring-blue-500',
    'secondary' => 'bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 focus:ring-blue-500',
    'danger'    => 'bg-red-600 hover:bg-red-700 text-white focus:ring-red-500',
    default     => 'bg-blue-600 hover:bg-blue-700 text-white focus:ring-blue-500',
};
@endphp

<{{ $tag }}
    @if($tag === 'button') type="{{ $type }}" @endif
    {{ $attributes->merge(['class' => "inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors $classes"]) }}
    @if($loading) disabled @endif
>
    @if($loading)
        <i class="ti ti-loader-2 animate-spin text-base"></i>
    @endif
    {{ $slot }}
</{{ $tag }}>
