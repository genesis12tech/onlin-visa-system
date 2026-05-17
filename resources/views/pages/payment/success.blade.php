<x-app-layout title="Payment Successful">
    <div class="max-w-2xl mx-auto space-y-6">

        {{-- Success header --}}
        <div class="text-center py-8">
            <div class="flex h-16 w-16 mx-auto items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30 mb-4">
                <i class="ti ti-circle-check text-3xl text-green-600 dark:text-green-400"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Payment Successful</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Your payment has been confirmed. Your application is now under review.
            </p>
        </div>

        {{-- Details card --}}
        <x-card>
            <div class="space-y-4">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500 dark:text-gray-400">Application reference</span>
                    <span class="font-mono font-semibold text-gray-900 dark:text-white">{{ $application->tracking_number }}</span>
                </div>
                @if($invoice)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Invoice number</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $invoice->invoice_number }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Amount paid</span>
                        <span class="font-semibold text-gray-900 dark:text-white">
                            {{ number_format($payment->amount_total / 100, 2) }} {{ $payment->currency }}
                        </span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Payment date</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $payment->succeeded_at?->format('d M Y H:i') ?? '—' }}</span>
                    </div>
                @endif
            </div>
        </x-card>

        {{-- Receipt section --}}
        @if($invoice)
            <x-card>
                @if($invoice->pdf_storage_path)
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">Payment Receipt</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Invoice {{ $invoice->invoice_number }}</p>
                        </div>
                        <x-button
                            tag="a"
                            :href="route('invoices.receipt', $invoice)"
                            variant="secondary"
                            aria-label="Download receipt PDF"
                        >
                            <i class="ti ti-download text-sm"></i>
                            Download Receipt
                        </x-button>
                    </div>
                @else
                    <div class="flex items-center gap-3">
                        <i class="ti ti-loader-2 animate-spin text-gray-400"></i>
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">Receipt being generated</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">You will receive it by email shortly.</p>
                        </div>
                    </div>
                @endif
            </x-card>
        @endif

        {{-- Actions --}}
        <div class="flex justify-center">
            <x-button tag="a" :href="route('dashboard')">
                Go to Dashboard
            </x-button>
        </div>

    </div>
</x-app-layout>
