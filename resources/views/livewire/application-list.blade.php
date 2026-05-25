<div>
    {{-- Header row --}}
    <div class="flex items-center justify-between mb-5">
        <h1 class="text-lg font-semibold" style="color: var(--portal-ink)">My Applications</h1>
        <a href="{{ route('applications.start') }}"
           class="inline-flex items-center gap-1.5 text-sm font-semibold px-4 py-2 rounded-lg text-white transition-opacity hover:opacity-90"
           style="background: var(--portal-teal)">
            <i class="ti ti-plus text-sm" aria-hidden="true"></i>
            New Application
        </a>
    </div>

    {{-- Filter tabs --}}
    @php
        $tabs = [
            'all'       => 'All',
            'approved'  => 'Approved',
            'in_review' => 'In Review',
            'submitted' => 'Submitted',
            'rejected'  => 'Rejected',
        ];
    @endphp
    <div class="flex flex-wrap gap-2 mb-5">
        @foreach($tabs as $key => $label)
            <button
                wire:click="setFilter('{{ $key }}')"
                class="text-sm font-medium px-4 py-1.5 rounded-full border transition-colors"
                style="{{ $filter === $key
                    ? 'background: var(--portal-teal); border-color: var(--portal-teal); color: #fff;'
                    : 'background: transparent; border-color: var(--portal-sand-3); color: var(--portal-ink-2);' }}"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Application cards --}}
    @forelse($applications as $application)
        @php $borderClass = $application->status->borderClass(); @endphp
        <button
            wire:click="$dispatch('openDetail', { applicationId: '{{ $application->ulid }}' })"
            class="w-full text-left block bg-white border border-[var(--portal-sand-3)] rounded-xl p-3.5 mb-2.5 hover:border-gray-300 transition-colors border-l-4 {{ $borderClass }}"
        >
            {{-- Top row: title + badge --}}
            <div class="flex items-start justify-between gap-3 mb-2">
                <div>
                    <p class="text-sm font-medium" style="color: var(--portal-ink)">
                        {{ $application->visaType->name }} — {{ $application->visaType->country->name }}
                    </p>
                    <p class="text-xs font-mono mt-0.5" style="color: var(--portal-ink-4)">
                        {{ $application->tracking_number }}
                    </p>
                </div>
                <x-status-badge :status="$application->status" />
            </div>

            {{-- Meta row --}}
            <div class="flex items-center gap-4 text-xs mb-2.5" style="color: var(--portal-ink-3)">
                @if($application->submitted_at)
                    <span class="flex items-center gap-1">
                        <i class="ti ti-calendar text-xs" aria-hidden="true"></i>
                        Submitted {{ $application->submitted_at->format('j M Y') }}
                    </span>
                    <span class="flex items-center gap-1">
                        <i class="ti ti-files text-xs" aria-hidden="true"></i>
                        {{ $application->acceptedDocumentsCount() }} / {{ $application->requiredDocumentsCount() }} docs
                    </span>
                @endif
            </div>

            {{-- Progress bar --}}
            <x-progress-bar
                :percent="$application->workflowProgressPercent()"
                :label="$application->workflowProgressLabel()"
                :colour="$application->workflowProgressColour()" />
        </button>
    @empty
        <x-empty-state
            icon="ti-file-certificate"
            heading="No applications found"
            description="{{ $filter === 'all' ? 'Start a new application to apply for a visa.' : 'No applications match the selected filter.' }}"
        >
            @if($filter === 'all')
                <x-slot name="cta">
                    <a href="{{ route('applications.start') }}"
                       class="inline-flex items-center gap-1.5 text-sm px-4 py-2 rounded-lg text-white"
                       style="background: var(--portal-teal)">
                        <i class="ti ti-plus" aria-hidden="true"></i>
                        New application
                    </a>
                </x-slot>
            @endif
        </x-empty-state>
    @endforelse

    {{-- Detail slide-over --}}
    @livewire('application-detail')
</div>
