@props(['icon' => 'ti-folder-open', 'heading' => 'Nothing here yet', 'description' => ''])

<div class="flex flex-col items-center justify-center py-16 text-center">
    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700 mb-4">
        <i class="ti {{ $icon }} text-3xl text-gray-400 dark:text-gray-500"></i>
    </div>
    <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-1">{{ $heading }}</h3>
    @if($description)
        <p class="text-sm text-gray-500 dark:text-gray-400 max-w-sm">{{ $description }}</p>
    @endif
    @isset($cta)
        <div class="mt-6">{{ $cta }}</div>
    @endisset
</div>
