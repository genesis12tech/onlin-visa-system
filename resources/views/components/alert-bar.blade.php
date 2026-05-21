<div {{ $attributes->merge(['class' => 'flex items-start gap-2.5 px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-950 border border-amber-200 dark:border-amber-800']) }}>
    <i class="ti ti-alert-triangle text-amber-600 dark:text-amber-400 text-sm flex-shrink-0 mt-0.5" aria-hidden="true"></i>
    <span class="text-sm text-amber-800 dark:text-amber-300">
        <strong>Action required</strong> on
        <span class="font-mono">{{ $application->tracking_number }}</span>
        @if($application->status === \App\Domain\Applications\Enums\ApplicationStatus::PaymentPending)
            — payment is required before your application can be processed.
            <a href="{{ route('applications.pay', $application->tracking_number) }}" class="underline font-medium">Complete payment</a>
        @elseif($application->status === \App\Domain\Applications\Enums\ApplicationStatus::Approved)
            — your visa has been approved. Download your decision letter from the application page.
        @elseif($docName = $application->latestRejectedDocumentName())
            — your {{ $docName }} was rejected. Please resubmit.
        @else
            — additional information requested.
        @endif
    </span>
</div>
