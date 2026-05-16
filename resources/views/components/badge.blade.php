<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium $colorClasses"]) }}>
    {{ $status?->label() ?? $slot }}
</span>
