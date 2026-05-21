<div {{ $attributes->merge(['class' => 'flex flex-col gap-2']) }}>
    @foreach($actions as $action)
        <a href="{{ $action['href'] }}"
           class="flex items-center gap-2 text-sm px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <i class="ti ti-{{ $action['icon'] }} text-sm" aria-hidden="true"></i>
            {{ $action['label'] }}
        </a>
    @endforeach
</div>
