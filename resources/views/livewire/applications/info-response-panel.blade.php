<div class="space-y-6">

    {{-- Officer request banner --}}
    @if($infoNote)
        <div class="rounded-xl border border-amber-200 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 p-5">
            <div class="flex items-start gap-3">
                <span class="flex-shrink-0 mt-0.5 flex h-8 w-8 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-800/50">
                    <i class="ti ti-info-circle text-amber-600 dark:text-amber-400 text-lg"></i>
                </span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-amber-900 dark:text-amber-100 mb-1">
                        Additional information requested
                    </p>
                    <p class="text-sm text-amber-800 dark:text-amber-200 whitespace-pre-line">{{ $infoNote->body }}</p>
                    @if(!empty($infoNote->metadata['deadline_date']))
                        <p class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                            <i class="ti ti-calendar-due text-xs"></i>
                            Please respond by <span class="font-semibold">{{ $infoNote->metadata['deadline_date'] }}</span>
                        </p>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Unlocked form fields --}}
    @if(count($sections) > 0)
        <div class="space-y-4">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                Update the fields below and then submit your response.
            </p>

            @foreach($sections as $section)
                <x-card :title="$section['title']">
                    <div class="space-y-5">
                        @foreach($section['fields'] as $field)
                            @php
                                $isRequired = $field['required'] ?? false;
                                $label = $field['label'];
                                $sectionKey = $section['key'];
                                $fieldKey = $field['key'];
                            @endphp

                            @if($field['type'] === 'select')
                                <x-select
                                    :name="$fieldKey"
                                    :label="$label"
                                    :required="$isRequired"
                                    wire:model.blur="answers.{{ $sectionKey }}.{{ $fieldKey }}"
                                >
                                    <option value="">Select…</option>
                                    @foreach($field['options'] ?? [] as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </x-select>

                            @elseif($field['type'] === 'textarea')
                                <x-textarea
                                    :name="$fieldKey"
                                    :label="$label"
                                    :required="$isRequired"
                                    wire:model.blur="answers.{{ $sectionKey }}.{{ $fieldKey }}"
                                />

                            @elseif($field['type'] === 'radio')
                                <fieldset>
                                    <legend class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ $label }}
                                        @if($isRequired)<span class="text-red-500 ml-0.5">*</span>@endif
                                    </legend>
                                    <div class="flex flex-wrap gap-4">
                                        @foreach($field['options'] ?? [] as $option)
                                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                                                <input
                                                    type="radio"
                                                    name="{{ $fieldKey }}"
                                                    value="{{ $option }}"
                                                    wire:model.live="answers.{{ $sectionKey }}.{{ $fieldKey }}"
                                                    class="text-blue-600 focus:ring-blue-500"
                                                >
                                                {{ ucfirst($option) }}
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>

                            @else
                                {{-- text, date, number --}}
                                <x-input
                                    :name="$fieldKey"
                                    :label="$label"
                                    :type="$field['type']"
                                    :required="$isRequired"
                                    wire:model.blur="answers.{{ $sectionKey }}.{{ $fieldKey }}"
                                />
                            @endif
                        @endforeach
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif

    {{-- Documents section --}}
    @php
        $hasPendingDocs = $application->documents()
            ->whereIn('status', ['pending', 'rejected', 'infected'])
            ->exists();
    @endphp

    @if($hasPendingDocs)
        <x-card title="Upload requested documents">
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                The officer has requested that you re-upload the documents below.
            </p>
            @livewire(
                'applications.document-upload-panel',
                ['applicationUlid' => $application->ulid],
                key('info-response-docs')
            )
        </x-card>
    @endif

    {{-- Submit error --}}
    @error('submit')
        <x-alert type="error" :dismissible="false">{{ $message }}</x-alert>
    @enderror

    {{-- Submit button --}}
    <div class="flex justify-end">
        <x-button
            wire:click="submitResponse"
            wire:loading.attr="disabled"
            wire:target="submitResponse"
        >
            <span wire:loading.remove wire:target="submitResponse">Submit response &rarr;</span>
            <span wire:loading wire:target="submitResponse">Submitting…</span>
        </x-button>
    </div>

</div>
