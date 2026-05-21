@if($message)
    <div {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 text-xs text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950 px-2 py-1 rounded-full']) }}>
        <i class="ti ti-alert-triangle text-xs" aria-hidden="true"></i>
        {{ $message }}
    </div>
@endif
