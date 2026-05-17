<div class="max-w-3xl mx-auto space-y-8">

    {{-- Header --}}
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">
                {{ $application->visaType->name }}
            </p>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Visa Application</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Reference: <span class="font-mono font-medium">{{ $application->tracking_number }}</span>
            </p>
        </div>
        <x-badge :status="$application->status" />
    </div>

    {{-- Submitted state --}}
    @if($application->status !== \App\Domain\Applications\Enums\ApplicationStatus::Draft)
        <x-card>
            <div class="text-center py-8 space-y-4">
                <div class="flex h-16 w-16 mx-auto items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                    <i class="ti ti-circle-check text-3xl text-green-600 dark:text-green-400"></i>
                </div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Application submitted</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
                    Your application has been received. Keep your tracking number safe — you'll need it to check your progress.
                </p>
                <div class="inline-block rounded-lg bg-gray-100 dark:bg-gray-700 px-6 py-3">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Tracking number</p>
                    <p class="text-xl font-mono font-bold text-gray-900 dark:text-white">{{ $application->tracking_number }}</p>
                </div>
                <div class="pt-4">
                    <a href="{{ route('dashboard') }}" class="text-sm text-blue-600 hover:text-blue-500">
                        &larr; Back to dashboard
                    </a>
                </div>
            </div>
        </x-card>
    @else
        {{-- Progress indicator --}}
        @php
            $sections = $this->sections();
            $totalSteps = count($sections) + 1; // +1 for review
            $currentStep = $onReviewStep ? $totalSteps : $currentSectionIndex + 1;
            $stepLabels = collect($sections)->pluck('title')->push('Review')->all();
        @endphp

        <x-step-indicator :steps="$stepLabels" :current="$currentStep" />

        {{-- Review step --}}
        @if($onReviewStep)
            <x-card title="Review your application">
                <div class="space-y-6">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Please review your answers before submitting. Once submitted, you cannot edit your application.
                    </p>

                    @foreach($sections as $section)
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">{{ $section['title'] }}</h4>
                            <dl class="space-y-1">
                                @foreach($section['fields'] as $field)
                                    @php $savedAnswers = $this->savedAnswersForSection($section['key']); @endphp
                                    <div class="flex gap-2 text-sm">
                                        <dt class="text-gray-500 dark:text-gray-400 min-w-40">{{ $field['label'] }}</dt>
                                        <dd class="text-gray-900 dark:text-white font-medium">
                                            {{ $savedAnswers[$field['key']] ?? '—' }}
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @endforeach

                    @if(!$this->canSubmit())
                        <x-alert type="warning" :dismissible="false">
                            All required documents must be uploaded before you can submit.
                        </x-alert>
                    @endif

                    <div class="flex items-center justify-between pt-2">
                        <x-button variant="secondary" wire:click="goBack">
                            &larr; Back
                        </x-button>

                        @if($this->canSubmit())
                            <x-button wire:click="submit" wire:loading.attr="disabled" wire:target="submit">
                                <span wire:loading.remove wire:target="submit">Submit application &rarr;</span>
                                <span wire:loading wire:target="submit">Submitting…</span>
                            </x-button>
                        @endif
                    </div>
                </div>
            </x-card>

        {{-- Form section step --}}
        @else
            @livewire(
                'applications.dynamic-form-section',
                [
                    'applicationUlid' => $application->ulid,
                    'section'         => $this->currentSection(),
                    'savedAnswers'    => $this->savedAnswersForSection($this->currentSection()['key'] ?? ''),
                ],
                key('section-' . $currentSectionIndex)
            )

            <div class="flex items-center justify-between">
                @if($currentSectionIndex > 0)
                    <x-button variant="secondary" wire:click="goBack">
                        &larr; Back
                    </x-button>
                @else
                    <a href="{{ route('dashboard') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                        &larr; Back to dashboard
                    </a>
                @endif

                <x-button wire:click="advance">
                    @if($currentSectionIndex < count($sections) - 1)
                        Save &amp; continue &rarr;
                    @else
                        Review application &rarr;
                    @endif
                </x-button>
            </div>
        @endif
    @endif

</div>
