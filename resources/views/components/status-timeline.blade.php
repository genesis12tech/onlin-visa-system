@props(['histories', 'publicOnly' => false])

@php
$items = $publicOnly
    ? $histories->whereNotNull('public_label')
    : $histories;
@endphp

@if($items->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400 italic">No status history yet.</p>
@else
    <ol class="relative border-l border-gray-200 dark:border-gray-700 ml-3">
        @foreach($items as $history)
            <li class="mb-6 ml-6">
                <span class="absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900 ring-8 ring-white dark:ring-gray-800">
                    <i class="ti ti-circle-check text-xs text-blue-600 dark:text-blue-300"></i>
                </span>
                <p class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ $publicOnly ? ($history->public_label ?? $history->to_status) : $history->to_status }}
                </p>
                <time class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $history->created_at->format('d M Y, H:i') }}
                </time>
            </li>
        @endforeach
    </ol>
@endif
