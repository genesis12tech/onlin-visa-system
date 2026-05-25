@props(['histories'])

@php
use App\Domain\Applications\Enums\ApplicationStatus;

$dotClasses = fn(string $status): string => match (ApplicationStatus::tryFrom($status)?->colour()) {
    'green'  => 'bg-green-100 ring-green-50',
    'red'    => 'bg-red-100 ring-red-50',
    'amber'  => 'bg-amber-100 ring-amber-50',
    'blue'   => 'bg-blue-100 ring-blue-50',
    'purple' => 'bg-purple-100 ring-purple-50',
    default  => 'bg-gray-100 ring-gray-50',
};

$iconClasses = fn(string $status): string => match (ApplicationStatus::tryFrom($status)?->colour()) {
    'green'  => 'text-green-600',
    'red'    => 'text-red-500',
    'amber'  => 'text-amber-600',
    'blue'   => 'text-blue-600',
    'purple' => 'text-purple-600',
    default  => 'text-gray-500',
};

$statusLabel = fn(string $status): string => ApplicationStatus::tryFrom($status)?->label()
    ?? ucfirst(str_replace('_', ' ', $status));
@endphp

@if($histories->isEmpty())
    <p class="text-sm italic" style="color: var(--portal-ink-4)">No history yet.</p>
@else
    <ol class="relative border-l ml-3" style="border-color: var(--portal-sand-3)">
        @foreach($histories as $history)
            <li class="mb-5 ml-6">
                <span class="absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full ring-8 {{ $dotClasses($history->to_status) }}">
                    <i class="ti ti-circle-check text-xs {{ $iconClasses($history->to_status) }}" aria-hidden="true"></i>
                </span>
                <p class="text-sm font-medium" style="color: var(--portal-ink)">{{ $statusLabel($history->to_status) }}</p>
                @if($history->reason)
                    <p class="text-xs mt-0.5" style="color: var(--portal-ink-3)">{{ $history->reason }}</p>
                @endif
                <time class="text-xs" style="color: var(--portal-ink-4)">
                    {{ $history->created_at->format('j M Y, H:i') }}
                </time>
            </li>
        @endforeach
    </ol>
@endif
