@props(['id', 'title' => ''])

<div
    x-data="{ open: false }"
    x-on:open-modal.window="if ($event.detail.id === '{{ $id }}') open = true"
    x-on:close-modal.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $id }}-title"
>
    <div class="absolute inset-0 bg-black/50" @click="open = false"></div>
    <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-lg w-full mx-4 p-6">
        @if($title)
            <div class="flex items-center justify-between mb-4">
                <h2 id="{{ $id }}-title" class="text-lg font-semibold text-gray-900 dark:text-white">{{ $title }}</h2>
                <button @click="open = false" aria-label="Close modal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="ti ti-x text-xl"></i>
                </button>
            </div>
        @endif
        {{ $slot }}
    </div>
</div>
