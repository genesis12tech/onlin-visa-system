@props(['steps', 'current'])

<nav aria-label="Progress">
    <ol class="flex items-center overflow-x-auto">
        @foreach($steps as $index => $label)
            @php
                $stepNumber = $index + 1;
                $isDone    = $stepNumber < $current;
                $isActive  = $stepNumber === $current;
            @endphp
            <li class="flex items-center flex-shrink-0 {{ !$loop->last ? 'flex-1' : '' }}">
                <span class="flex items-center gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium flex-shrink-0
                        {{ $isDone   ? 'bg-blue-600 text-white' : '' }}
                        {{ $isActive ? 'border-2 border-blue-600 text-blue-600 dark:text-blue-400' : '' }}
                        {{ !$isDone && !$isActive ? 'border-2 border-gray-300 dark:border-gray-600 text-gray-500' : '' }}">
                        @if($isDone)
                            <i class="ti ti-check text-sm"></i>
                        @else
                            {{ $stepNumber }}
                        @endif
                    </span>
                    <span class="text-sm font-medium
                        {{ $isActive ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400' }}
                        whitespace-nowrap">
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
