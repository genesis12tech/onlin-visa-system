<div>
    <div class="flex items-center justify-between mb-5">
        <h1 class="text-lg font-semibold text-gray-900 dark:text-white">Documents</h1>
    </div>

    @if(empty($documentsByApplication))
        <x-empty-state
            icon="ti-file-off"
            heading="No documents yet"
            description="Documents will appear here once you start an application."
        />
    @else
        <div class="space-y-6">
            @foreach($documentsByApplication as $group)
                @php $application = $group['application']; @endphp
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ $application['visa_type_name'] }} — {{ $application['country_name'] }}
                            </p>
                            <p class="text-xs font-mono text-gray-400 dark:text-gray-500 mt-0.5">
                                {{ $application['tracking_number'] }}
                            </p>
                        </div>
                        <a href="{{ route('applications.wizard', $application['tracking_number']) }}"
                           class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
                            View application &rarr;
                        </a>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($group['documents'] as $document)
                            @php
                                $statusClass = match ($document['status']) {
                                    \App\Domain\Documents\Enums\DocumentStatus::Accepted => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                    \App\Domain\Documents\Enums\DocumentStatus::Rejected,
                                    \App\Domain\Documents\Enums\DocumentStatus::Infected => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                    \App\Domain\Documents\Enums\DocumentStatus::UnderReview,
                                    \App\Domain\Documents\Enums\DocumentStatus::PendingScan => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                    default => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
                                };
                            @endphp
                            <div class="flex items-center gap-3 px-4 py-3">
                                <i class="ti ti-file text-gray-400 dark:text-gray-500 flex-shrink-0 text-sm" aria-hidden="true"></i>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-900 dark:text-white">
                                        {{ $document['type_name'] }}
                                    </p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                        Updated {{ $document['updated_at'] }}
                                    </p>
                                </div>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $statusClass }}">
                                    {{ $document['status']->label() }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
