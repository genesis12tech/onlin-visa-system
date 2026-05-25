<div {{ $attributes->merge(['class' => 'bg-white border border-[var(--portal-sand-3)] rounded-xl p-4']) }}>
    <p class="text-2xl font-bold mb-0.5 {{ $numberColour ?? ($highlight ? $highlightColour : 'text-gray-900') }}">
        {{ $value }}
    </p>
    <p class="text-xs text-gray-500">{{ $label }}</p>
    @if($sub)
        <p class="text-xs mt-1.5 font-medium {{ $numberColour ?? 'text-gray-400' }}">{{ $sub }}</p>
    @endif
</div>
