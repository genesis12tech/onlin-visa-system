<div
    x-data="{ progress: 0, uploading: false }"
    x-on:livewire-upload-start="uploading = true"
    x-on:livewire-upload-finish="uploading = false; progress = 0"
    x-on:livewire-upload-error="uploading = false"
    x-on:livewire-upload-progress="progress = $event.detail.progress"
>
    {{-- Polling trigger --}}
    @if($pollingActive)
        <div wire:poll.3000ms="refreshDocuments" class="hidden" aria-hidden="true"></div>
    @endif

    {{-- Upload error --}}
    @if($uploadError)
        <x-alert type="error" :dismissible="false" class="mb-4">{{ $uploadError }}</x-alert>
    @endif

    {{-- Check back later (polling timeout) --}}
    @if($showCheckBackLater)
        <x-alert type="warning" :dismissible="false" class="mb-4">
            Document scanning is taking longer than expected. Please check back later or refresh the page.
        </x-alert>
    @endif

    {{-- Shared hidden file input — one upload at a time --}}
    <input
        type="file"
        x-ref="fileInput"
        class="sr-only"
        aria-hidden="true"
        x-on:change="
            let file = $event.target.files[0];
            if (file) {
                $event.target.value = '';
                $wire.upload('pendingFile', file, () => {}, () => {});
            }
        "
    >

    {{-- Upload progress overlay --}}
    <div
        x-show="uploading"
        x-transition
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
        aria-live="polite"
    >
        <div class="bg-white dark:bg-gray-800 rounded-xl p-8 flex flex-col items-center gap-4 shadow-2xl">
            {{-- Circular progress ring --}}
            <svg class="w-20 h-20 -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
                <circle
                    class="stroke-gray-200 dark:stroke-gray-700 fill-none"
                    cx="18" cy="18" r="15.9" stroke-width="3.2"
                />
                <circle
                    class="stroke-blue-600 fill-none transition-all duration-300"
                    cx="18" cy="18" r="15.9" stroke-width="3.2"
                    stroke-linecap="round"
                    :stroke-dasharray="100"
                    :stroke-dashoffset="100 - progress"
                />
            </svg>
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                Uploading… <span x-text="Math.round(progress) + '%'"></span>
            </p>
        </div>
    </div>

    {{-- Document slot cards --}}
    <div class="space-y-4">
        @foreach($documents as $document)
            @php
                $isUploadable = in_array($document->status->value, ['pending', 'rejected', 'infected']);
                $statusColor = match($document->status) {
                    \App\Domain\Documents\Enums\DocumentStatus::Accepted => 'green',
                    \App\Domain\Documents\Enums\DocumentStatus::Rejected,
                    \App\Domain\Documents\Enums\DocumentStatus::Infected => 'red',
                    \App\Domain\Documents\Enums\DocumentStatus::Uploaded,
                    \App\Domain\Documents\Enums\DocumentStatus::UnderReview => 'blue',
                    \App\Domain\Documents\Enums\DocumentStatus::PendingScan => 'purple',
                    default => 'gray',
                };
                $statusIcon = match($document->status) {
                    \App\Domain\Documents\Enums\DocumentStatus::Accepted => 'ti-circle-check',
                    \App\Domain\Documents\Enums\DocumentStatus::Rejected,
                    \App\Domain\Documents\Enums\DocumentStatus::Infected => 'ti-circle-x',
                    \App\Domain\Documents\Enums\DocumentStatus::PendingScan => 'ti-loader-2',
                    \App\Domain\Documents\Enums\DocumentStatus::Uploaded,
                    \App\Domain\Documents\Enums\DocumentStatus::UnderReview => 'ti-file-check',
                    default => 'ti-file',
                };
                $iconBg = match($statusColor) {
                    'green' => 'bg-green-100 dark:bg-green-900/30',
                    'red' => 'bg-red-100 dark:bg-red-900/30',
                    'blue' => 'bg-blue-100 dark:bg-blue-900/30',
                    'purple' => 'bg-purple-100 dark:bg-purple-900/30',
                    default => 'bg-gray-100 dark:bg-gray-700',
                };
                $iconColor = match($statusColor) {
                    'green' => 'text-green-600 dark:text-green-400',
                    'red' => 'text-red-600 dark:text-red-400',
                    'blue' => 'text-blue-600 dark:text-blue-400',
                    'purple' => 'text-purple-600 dark:text-purple-400',
                    default => 'text-gray-400',
                };
            @endphp

            <x-card>
                <div class="flex items-start gap-4">
                    {{-- Status icon --}}
                    <div class="flex-shrink-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full {{ $iconBg }}">
                            <i class="ti {{ $statusIcon }} text-xl {{ $iconColor }} @if($document->status === \App\Domain\Documents\Enums\DocumentStatus::PendingScan) animate-spin @endif"></i>
                        </div>
                    </div>

                    {{-- Document info --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $document->documentType->name }}
                            </p>
                            <x-badge :color="$statusColor">{{ $document->status->label() }}</x-badge>
                        </div>

                        @if($document->documentType->description)
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                                {{ $document->documentType->description }}
                            </p>
                        @endif

                        @if($document->currentVersion)
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $document->currentVersion->original_filename }}
                                &middot; {{ number_format($document->currentVersion->file_size_bytes / 1024, 0) }} KB
                            </p>
                        @endif

                        {{-- Rejection reason --}}
                        @if($document->status === \App\Domain\Documents\Enums\DocumentStatus::Rejected && $document->rejection_reason)
                            <div class="mt-2 rounded-md bg-red-50 dark:bg-red-900/20 px-3 py-2">
                                <p class="text-xs text-red-700 dark:text-red-300">
                                    <span class="font-semibold">Rejected:</span> {{ $document->rejection_reason }}
                                </p>
                            </div>
                        @endif

                        {{-- Infected message --}}
                        @if($document->status === \App\Domain\Documents\Enums\DocumentStatus::Infected)
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                This file failed the security scan. Please upload a different file.
                            </p>
                        @endif

                        {{-- Scanning message --}}
                        @if($document->currentVersion?->scan_status === \App\Domain\Documents\Enums\ScanStatus::Pending)
                            <p class="mt-1 text-xs text-purple-600 dark:text-purple-400">
                                <i class="ti ti-loader-2 animate-spin text-xs"></i> Scanning document for security…
                            </p>
                        @endif
                    </div>

                    {{-- Actions column --}}
                    <div class="flex-shrink-0 flex flex-col items-end gap-2">
                        {{-- Download link (clean docs only) --}}
                        @if(isset($downloadUrls[$document->ulid]))
                            <x-button
                                tag="a"
                                :href="$downloadUrls[$document->ulid]"
                                variant="secondary"
                                class="text-xs"
                                aria-label="Download {{ $document->documentType->name }}"
                            >
                                <i class="ti ti-download text-sm"></i>
                                Download
                            </x-button>
                        @endif

                        {{-- Upload / replace button --}}
                        @if($isUploadable)
                            <x-button
                                variant="{{ $document->status->value === 'pending' ? 'primary' : 'secondary' }}"
                                class="text-xs"
                                wire:loading.attr="disabled"
                                aria-label="{{ $document->status->value === 'pending' ? 'Upload' : 'Replace' }} {{ $document->documentType->name }}"
                                x-on:click="
                                    $wire.call('selectDocument', '{{ $document->ulid }}')
                                        .then(() => { $refs.fileInput.value = ''; $refs.fileInput.click(); })
                                "
                            >
                                <i class="ti ti-upload text-sm"></i>
                                {{ $document->status->value === 'pending' ? 'Upload' : 'Replace' }}
                            </x-button>
                        @endif
                    </div>
                </div>

                {{-- Drag-and-drop zone (uploadable slots only) --}}
                @if($isUploadable)
                    <div
                        class="mt-3 cursor-pointer rounded-lg border-2 border-dashed border-gray-300 p-4 text-center text-sm text-gray-500 transition-colors hover:border-blue-400 hover:bg-blue-50 dark:border-gray-600 dark:text-gray-400 dark:hover:border-blue-500 dark:hover:bg-blue-900/10"
                        x-on:dragover.prevent="$el.classList.add('border-blue-400', 'bg-blue-50', 'dark:bg-blue-900/20')"
                        x-on:dragleave.prevent="$el.classList.remove('border-blue-400', 'bg-blue-50', 'dark:bg-blue-900/20')"
                        x-on:drop.prevent="
                            $el.classList.remove('border-blue-400', 'bg-blue-50', 'dark:bg-blue-900/20');
                            let file = $event.dataTransfer.files[0];
                            if (file) {
                                $wire.call('selectDocument', '{{ $document->ulid }}')
                                    .then(() => $wire.upload('pendingFile', file, () => {}, () => {}));
                            }
                        "
                        x-on:click="
                            $wire.call('selectDocument', '{{ $document->ulid }}')
                                .then(() => { $refs.fileInput.value = ''; $refs.fileInput.click(); })
                        "
                        role="button"
                        tabindex="0"
                        aria-label="Upload {{ $document->documentType->name }}"
                    >
                        <i class="ti ti-cloud-upload text-2xl mb-1 block"></i>
                        <p>Drag &amp; drop or <span class="text-blue-600 underline dark:text-blue-400">click to browse</span></p>
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                            Accepted: {{ implode(', ', $document->documentType->accepted_mime_types) }}
                            &middot; Max {{ number_format($document->documentType->max_size_kb / 1024, 1) }} MB
                        </p>
                    </div>
                @endif
            </x-card>
        @endforeach
    </div>
</div>
