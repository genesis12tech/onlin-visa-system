<div>
    <x-card :title="$section['title']">
        <div class="space-y-5">

            {{-- Saved indicator --}}
            @if($saving)
                <div class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500">
                    <i class="ti ti-loader-2 animate-spin"></i> Saving…
                </div>
            @elseif($saved)
                <div
                    x-data="{ show: true }"
                    x-init="setTimeout(() => show = false, 2000)"
                    x-show="show"
                    class="flex items-center gap-1.5 text-xs text-green-600 dark:text-green-400"
                >
                    <i class="ti ti-circle-check"></i> Saved
                </div>
            @endif

            @foreach($section['fields'] as $field)
                @if($visibleFields[$field['key']])
                    @php
                        $fieldName = "answers.{$field['key']}";
                        $isRequired = $field['required'] ?? false;
                        $label = $field['label'];
                    @endphp

                    @if($field['type'] === 'select')
                        <x-select
                            :name="$field['key']"
                            :label="$label"
                            :required="$isRequired"
                            wire:model.blur="answers.{{ $field['key'] }}"
                        >
                            <option value="">Select…</option>
                            @foreach($field['options'] ?? [] as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </x-select>

                    @elseif($field['type'] === 'textarea')
                        <x-textarea
                            :name="$field['key']"
                            :label="$label"
                            :required="$isRequired"
                            wire:model.blur="answers.{{ $field['key'] }}"
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
                                            name="{{ $field['key'] }}"
                                            value="{{ $option }}"
                                            wire:model.live="answers.{{ $field['key'] }}"
                                            class="text-blue-600 focus:ring-blue-500"
                                        >
                                        {{ ucfirst($option) }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                    @elseif($field['type'] === 'checkbox')
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                wire:model.live="answers.{{ $field['key'] }}"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            >
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                {{ $label }}
                                @if($isRequired)<span class="text-red-500 ml-0.5">*</span>@endif
                            </span>
                        </label>

                    @else
                        {{-- text, date, number --}}
                        <x-input
                            :name="$field['key']"
                            :label="$label"
                            :type="$field['type']"
                            :required="$isRequired"
                            wire:model.blur="answers.{{ $field['key'] }}"
                        />
                    @endif
                @endif
            @endforeach

        </div>
    </x-card>
</div>
