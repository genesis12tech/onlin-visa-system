<div class="max-w-2xl mx-auto space-y-6">

    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Complete your payment</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ $application->visaType->name }} &middot;
            <span class="font-mono">{{ $application->tracking_number }}</span>
        </p>
    </div>

    {{-- Error --}}
    @if($error)
        <x-alert type="error" :dismissible="false">{{ $error }}</x-alert>
    @endif

    {{-- Fee breakdown card --}}
    <x-card title="Payment summary">
        <div class="divide-y divide-gray-100 dark:divide-gray-700">
            @foreach($feeData['items'] as $item)
                <div class="flex justify-between py-3 text-sm">
                    <span class="text-gray-700 dark:text-gray-300">{{ $item['description'] }}</span>
                    <span class="font-semibold text-gray-900 dark:text-white tabular-nums">
                        {{ number_format($item['unit_amount'] / 100, 2) }} {{ $feeData['currency'] }}
                    </span>
                </div>
            @endforeach
        </div>

        {{-- Total --}}
        <div class="flex justify-between pt-4 border-t border-gray-200 dark:border-gray-600">
            <span class="text-base font-bold text-gray-900 dark:text-white">Total</span>
            <span class="text-base font-bold text-gray-900 dark:text-white tabular-nums">
                {{ number_format($feeData['total_amount'] / 100, 2) }} {{ $feeData['currency'] }}
            </span>
        </div>
    </x-card>

    {{-- Priority toggle --}}
    @if($feeData['has_priority_option'])
        <x-card>
            <div class="flex items-start gap-4">
                <div class="flex-1">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Priority Processing</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Move your application to the front of the queue. Processing time is reduced significantly.
                    </p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer mt-0.5" aria-label="Enable priority processing">
                    <input
                        type="checkbox"
                        class="sr-only peer"
                        wire:model.live="priorityEnabled"
                    >
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-500 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                </label>
            </div>
        </x-card>
    @endif

    {{-- Pay button --}}
    <div class="flex items-center justify-between">
        <a href="{{ route('dashboard') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
            &larr; Back to dashboard
        </a>

        <x-button
            wire:click="initiatePayment"
            wire:loading.attr="disabled"
            wire:target="initiatePayment"
        >
            <span wire:loading.remove wire:target="initiatePayment">
                <i class="ti ti-credit-card text-sm"></i>
                Pay {{ number_format($feeData['total_amount'] / 100, 2) }} {{ $feeData['currency'] }} securely
            </span>
            <span wire:loading wire:target="initiatePayment">
                Redirecting to payment…
            </span>
        </x-button>
    </div>

    {{-- Stripe trust badge --}}
    <p class="text-center text-xs text-gray-400 dark:text-gray-500">
        <i class="ti ti-lock text-xs"></i>
        Payments are processed securely by Stripe. We never store your card details.
    </p>

</div>
