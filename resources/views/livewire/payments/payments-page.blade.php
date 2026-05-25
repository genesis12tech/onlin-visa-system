<div>
    <div class="flex items-center justify-between mb-5">
        <h1 class="text-lg font-semibold text-gray-900 dark:text-white">Payments</h1>
    </div>

    {{-- Pending alert bar --}}
    @if($pendingCount > 0)
        <div class="flex items-start gap-2.5 px-4 py-3 rounded-xl mb-5
                    bg-amber-50 dark:bg-amber-950 border border-amber-200 dark:border-amber-800">
            <i class="ti ti-alert-triangle text-amber-600 dark:text-amber-400 text-sm flex-shrink-0 mt-0.5"
               aria-hidden="true"></i>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">
                    {{ $pendingCount }} {{ Str::plural('payment', $pendingCount) }} pending
                </p>
                <div class="mt-1 space-y-1">
                    @foreach($pendingApplications as $pending)
                        <p class="text-sm text-amber-700 dark:text-amber-400">
                            <span class="font-mono">{{ $pending['tracking_number'] }}</span>
                            — {{ $pending['visa_type_name'] }}
                            <a href="{{ $pending['pay_url'] }}"
                               class="ml-2 underline font-medium">Pay now</a>
                        </p>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if(empty($payments))
        <x-empty-state
            icon="ti-credit-card-off"
            heading="No payments yet"
            description="Payment history will appear here once you make a payment." />
    @else
        <x-card>
            <div class="divide-y divide-gray-100 dark:divide-gray-700 -mx-6 -my-4">
                @foreach($payments as $payment)
                    @php
                        $statusClass = match ($payment['status']) {
                            'succeeded' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                            'failed' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                            'refunded', 'partially_refunded' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                            default => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                        };
                    @endphp
                    <div class="flex items-center gap-4 px-6 py-4">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ $payment['visa_type_name'] }}
                            </p>
                            <p class="text-xs font-mono text-gray-400 dark:text-gray-500 mt-0.5">
                                {{ $payment['tracking_number'] }}
                            </p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $payment['currency'] }} {{ $payment['amount'] }}
                            </p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                {{ $payment['provider'] }} · {{ $payment['date'] }}
                            </p>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium flex-shrink-0 {{ $statusClass }}">
                            {{ $payment['status_label'] }}
                        </span>
                        <div class="flex-shrink-0 w-20 text-right">
                            @if($payment['has_receipt_pdf'] && $payment['invoice_ulid'])
                                <a href="{{ route('invoices.receipt', $payment['invoice_ulid']) }}"
                                   class="text-xs font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400">
                                    Receipt
                                </a>
                            @elseif($payment['pay_url'])
                                <a href="{{ $payment['pay_url'] }}"
                                   class="text-xs font-medium text-teal-600 hover:text-teal-700 dark:text-teal-400">
                                    Pay Now
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif
</div>
