<a href="{{ route('applications.wizard', $application->tracking_number) }}"
   {{ $attributes->merge(['class' => 'block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-3.5 hover:border-gray-300 dark:hover:border-gray-600 transition-colors']) }}>

    {{-- Top row: title + badge --}}
    <div class="flex items-start justify-between gap-3 mb-2">
        <div>
            <p class="text-sm font-medium text-gray-900 dark:text-white">
                {{ $application->visaType->name }} — {{ $application->visaType->country->name }}
            </p>
            <p class="text-xs text-gray-400 dark:text-gray-500 font-mono mt-0.5">
                {{ $application->tracking_number }}
            </p>
        </div>
        <x-status-badge :status="$application->status" />
    </div>

    {{-- Meta row --}}
    <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400 mb-2.5">
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

    {{-- Action tag (only when a document is rejected) --}}
    @if($rejectionReason = $application->latestRejectedDocumentName())
        <x-action-tag :message="$rejectionReason . ' — resubmission required'" class="mb-2.5" />
    @endif

    {{-- Progress bar --}}
    <x-progress-bar
        :percent="$application->workflowProgressPercent()"
        :label="$application->workflowProgressLabel()"
        :colour="$application->workflowProgressColour()" />

</a>
