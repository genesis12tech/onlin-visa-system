<div class="space-y-6">

    {{-- Status header --}}
    <x-card>
        <div class="flex items-start gap-5">
            <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-full {{ $statusInfo['bgClass'] }}">
                <i class="ti {{ $statusInfo['icon'] }} text-2xl {{ $statusInfo['iconClass'] }}"></i>
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $application->status->label() }}</h2>
                    <x-badge :status="$application->status" />
                </div>
                @if($statusInfo['description'])
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $statusInfo['description'] }}</p>
                @endif
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-gray-400 dark:text-gray-500">
                    <span class="font-mono">{{ $application->tracking_number }}</span>
                    <span>&middot;</span>
                    <span>{{ $application->visaType->name }}, {{ $application->visaType->country->name }}</span>
                    @if($application->submitted_at)
                        <span>&middot;</span>
                        <span>Submitted {{ $application->submitted_at->format('d M Y') }}</span>
                    @endif
                </div>
            </div>
        </div>
    </x-card>

    {{-- Decision details --}}
    @if($isDecided)
        <x-card title="Decision details">
            <div class="space-y-3">
                @if($application->decision_at)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Decision date</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ $application->decision_at->format('d M Y') }}</span>
                    </div>
                @endif
                @if($application->decision_reason)
                    <div class="text-sm">
                        <p class="mb-1 text-gray-500 dark:text-gray-400">Reason</p>
                        <p class="text-gray-900 dark:text-white">{{ $application->decision_reason }}</p>
                    </div>
                @endif
                @if($isApproved)
                    @if($application->validity_period)
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Validity period</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $application->validity_period }}</span>
                        </div>
                    @endif
                    @if($application->entry_type)
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Entry type</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $application->entry_type }}</span>
                        </div>
                    @endif
                @endif
            </div>
        </x-card>
    @endif

    {{-- Appointment --}}
    @if($appointment)
        <x-card title="Appointment">
            <div class="space-y-3">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500 dark:text-gray-400">Date &amp; time</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ $appointment->appointment_at->format('d M Y, H:i') }}</span>
                </div>
                @if($appointment->type)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Type</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ ucfirst($appointment->type) }}</span>
                    </div>
                @endif
                @if($appointment->location)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Location</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ $appointment->location }}</span>
                    </div>
                @endif
                @if($appointment->instructions)
                    <div class="text-sm">
                        <p class="mb-1 text-gray-500 dark:text-gray-400">Instructions</p>
                        <p class="whitespace-pre-line text-gray-900 dark:text-white">{{ $appointment->instructions }}</p>
                    </div>
                @endif
            </div>
        </x-card>
    @endif

    {{-- Officer notes visible to applicant --}}
    @if($application->notes->isNotEmpty())
        <x-card title="Messages from your case officer">
            <div class="space-y-4">
                @foreach($application->notes as $note)
                    <div class="border-b border-gray-100 pb-4 last:border-0 last:pb-0 dark:border-gray-700">
                        <p class="whitespace-pre-line text-sm text-gray-900 dark:text-white">{{ $note->body }}</p>
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ $note->created_at->format('d M Y, H:i') }}</p>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    {{-- Status history --}}
    @if($statusHistories->isNotEmpty())
        <x-card title="Application history">
            <ol class="relative ml-3 border-l border-gray-200 dark:border-gray-700">
                @foreach($statusHistories as $history)
                    <li class="mb-6 ml-6 last:mb-0">
                        <span class="absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 ring-8 ring-white dark:bg-blue-900 dark:ring-gray-800">
                            <i class="ti ti-circle-check text-xs text-blue-600 dark:text-blue-300"></i>
                        </span>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ $history['label'] }}
                        </p>
                        <time class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $history['created_at']->format('d M Y, H:i') }}
                        </time>
                    </li>
                @endforeach
            </ol>
        </x-card>
    @endif

    {{-- Footer actions --}}
    <div class="flex items-center justify-between">
        <a href="{{ route('dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
            &larr; Back to dashboard
        </a>

        @if($latestPayment?->invoice?->pdf_storage_path)
            <x-button tag="a" :href="route('invoices.receipt', $latestPayment->invoice)" variant="secondary">
                <i class="ti ti-download text-sm"></i>
                Download Receipt
            </x-button>
        @endif
    </div>

</div>
