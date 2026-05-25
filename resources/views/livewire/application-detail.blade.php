@php
use App\Domain\Applications\Enums\ApplicationStatus;

$isCancellable = $application && in_array($application->status, [
    ApplicationStatus::Draft,
    ApplicationStatus::Submitted,
    ApplicationStatus::PaymentPending,
], strict: true);

$isApproved = $application && $application->status === ApplicationStatus::Approved;
@endphp

<div>
    @if($isOpen && $application)
        {{-- Backdrop --}}
        <div
            wire:click="close"
            class="fixed inset-0 z-40 bg-black/30 backdrop-blur-sm"
            aria-hidden="true"
        ></div>

        {{-- Slide-over panel --}}
        <div
            class="fixed inset-y-0 right-0 z-50 flex flex-col bg-white shadow-2xl"
            style="width: 520px; max-width: 100vw;"
            role="dialog"
            aria-modal="true"
            aria-label="Application details"
            x-data
            x-init="$el.style.transform = 'translateX(100%)'; requestAnimationFrame(() => { $el.style.transition = 'transform 0.3s ease'; $el.style.transform = 'translateX(0)'; })"
        >
            {{-- Panel header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b" style="border-color: var(--portal-sand-3)">
                <div>
                    <p class="text-sm font-bold" style="color: var(--portal-ink)">Application Details</p>
                    <p class="text-xs font-mono mt-0.5" style="color: var(--portal-ink-4)">
                        {{ $application->tracking_number }}
                    </p>
                </div>
                <button
                    wire:click="close"
                    class="flex items-center justify-center w-8 h-8 rounded-lg transition-colors hover:bg-gray-100"
                    aria-label="Close panel"
                >
                    <i class="ti ti-x text-sm" style="color: var(--portal-ink-3)" aria-hidden="true"></i>
                </button>
            </div>

            {{-- Scrollable body --}}
            <div class="flex-1 overflow-y-auto px-5 py-5 space-y-6">

                {{-- Status section --}}
                <section>
                    <p class="text-xs font-semibold uppercase tracking-wide mb-3" style="color: var(--portal-ink-4)">Status</p>
                    <div class="flex items-center gap-3">
                        <x-status-badge :status="$application->status" />
                        <span class="text-sm" style="color: var(--portal-ink-2)">{{ $application->status->label() }}</span>
                    </div>
                    @if($application->decision_reason)
                        <p class="text-sm mt-2 p-3 rounded-lg" style="background: var(--portal-sand); color: var(--portal-ink-2)">
                            {{ $application->decision_reason }}
                        </p>
                    @endif
                </section>

                {{-- Application Details section --}}
                <section>
                    <p class="text-xs font-semibold uppercase tracking-wide mb-3" style="color: var(--portal-ink-4)">Application Details</p>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3">
                        <div>
                            <dt class="text-xs" style="color: var(--portal-ink-4)">Visa type</dt>
                            <dd class="text-sm font-medium mt-0.5" style="color: var(--portal-ink)">{{ $application->visaType->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs" style="color: var(--portal-ink-4)">Country</dt>
                            <dd class="text-sm font-medium mt-0.5" style="color: var(--portal-ink)">{{ $application->visaType->country->name }}</dd>
                        </div>
                        @if($application->submitted_at)
                            <div>
                                <dt class="text-xs" style="color: var(--portal-ink-4)">Submitted</dt>
                                <dd class="text-sm font-medium mt-0.5" style="color: var(--portal-ink)">{{ $application->submitted_at->format('j M Y') }}</dd>
                            </div>
                        @endif
                        @if($application->travel_date)
                            <div>
                                <dt class="text-xs" style="color: var(--portal-ink-4)">Travel date</dt>
                                <dd class="text-sm font-medium mt-0.5" style="color: var(--portal-ink)">{{ $application->travel_date->format('j M Y') }}</dd>
                            </div>
                        @endif
                        @if($application->decision_at)
                            <div>
                                <dt class="text-xs" style="color: var(--portal-ink-4)">Decision date</dt>
                                <dd class="text-sm font-medium mt-0.5" style="color: var(--portal-ink)">{{ $application->decision_at->format('j M Y') }}</dd>
                            </div>
                        @endif
                        @if($application->validity_period)
                            <div>
                                <dt class="text-xs" style="color: var(--portal-ink-4)">Validity</dt>
                                <dd class="text-sm font-medium mt-0.5" style="color: var(--portal-ink)">{{ $application->validity_period }}</dd>
                            </div>
                        @endif
                    </dl>
                </section>

                {{-- Documents section --}}
                @if($application->documents->isNotEmpty())
                    <section>
                        <p class="text-xs font-semibold uppercase tracking-wide mb-3" style="color: var(--portal-ink-4)">Documents</p>
                        <div class="space-y-2">
                            @foreach($application->documents as $document)
                                <div class="flex items-center gap-3 rounded-lg p-3" style="background: var(--portal-sand)">
                                    <i class="ti ti-file text-sm flex-shrink-0" style="color: var(--portal-ink-3)" aria-hidden="true"></i>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium truncate" style="color: var(--portal-ink)">
                                            {{ $document->documentType?->name ?? 'Document' }}
                                        </p>
                                    </div>
                                    <x-status-badge :status="$document->status->value" />
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- Application History section --}}
                <section>
                    <p class="text-xs font-semibold uppercase tracking-wide mb-3" style="color: var(--portal-ink-4)">Application History</p>
                    <x-timeline :histories="$application->statusHistories" />
                </section>

            </div>

            {{-- Footer --}}
            @if($isApproved || $isCancellable)
                <div class="border-t px-5 py-4 flex flex-col gap-2.5" style="border-color: var(--portal-sand-3)">
                    @if($isApproved && $application->decision_letter_pdf_path)
                        <a
                            href="{{ route('applications.wizard', $application->tracking_number) }}"
                            class="inline-flex items-center justify-center gap-2 text-sm font-semibold px-4 py-2.5 rounded-lg text-white transition-opacity hover:opacity-90"
                            style="background: var(--portal-teal)"
                        >
                            <i class="ti ti-download text-sm" aria-hidden="true"></i>
                            Download Approval Letter
                        </a>
                    @endif
                    @if($isCancellable)
                        <button
                            wire:click="cancelApplication"
                            wire:confirm="Are you sure you want to cancel this application? This cannot be undone."
                            class="inline-flex items-center justify-center gap-2 text-sm font-semibold px-4 py-2.5 rounded-lg border border-red-400 text-red-600 transition-colors hover:bg-red-50"
                        >
                            <i class="ti ti-x text-sm" aria-hidden="true"></i>
                            Cancel Application
                        </button>
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>
