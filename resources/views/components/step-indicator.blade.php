@props(['steps', 'current', 'color' => 'blue'])

@php
$done = [
    'blue' => 'bg-blue-600 text-white',
    'teal' => 'bg-teal-600 text-white',
][$color] ?? 'bg-blue-600 text-white';

$active = [
    'blue' => 'border-2 border-blue-600 text-blue-600 dark:text-blue-400',
    'teal' => 'border-2 border-teal-600 text-teal-600 dark:text-teal-400',
][$color] ?? 'border-2 border-blue-600 text-blue-600 dark:text-blue-400';

$activeLabel = [
    'blue' => 'text-blue-600 dark:text-blue-400',
    'teal' => 'text-teal-600 dark:text-teal-400',
][$color] ?? 'text-blue-600 dark:text-blue-400';

$pending      = 'border-2 border-stone-300 dark:border-stone-600 text-stone-500 dark:text-stone-400';
$pendingLabel = 'text-stone-500 dark:text-stone-400';
@endphp

<nav aria-label="Progress">
    <ol class="flex items-center overflow-x-auto">
        @foreach($steps as $index => $label)
            @php
                $stepNumber = $index + 1;
                $isDone     = $stepNumber < $current;
                $isActive   = $stepNumber === $current;
            @endphp
            <li class="flex items-center flex-shrink-0 {{ !$loop->last ? 'flex-1' : '' }}">
                <span class="flex items-center gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium flex-shrink-0
                        {{ $isDone ? $done : ($isActive ? $active : $pending) }}">
                        @if($isDone)
                            <i class="ti ti-check text-sm"></i>
                        @else
                            {{ $stepNumber }}
                        @endif
                    </span>
                    <span class="text-sm font-medium whitespace-nowrap
                        {{ $isActive ? $activeLabel : $pendingLabel }}">
                        {{ $label }}
                    </span>
                </span>
                @if(!$loop->last)
                    <div class="flex-1 mx-3 h-px bg-gray-200 dark:bg-gray-700 min-w-[2rem]"></div>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
