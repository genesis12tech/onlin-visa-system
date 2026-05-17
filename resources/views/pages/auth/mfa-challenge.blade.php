<x-guest-layout title="Two-factor verification">
    <div class="w-full max-w-md space-y-4">

        <x-card>
            <div class="space-y-6">

                {{-- Logo --}}
                <div class="flex justify-center">
                    <span class="text-2xl font-bold tracking-tight text-blue-600 dark:text-blue-400">
                        {{ config('app.name') }}
                    </span>
                </div>

                {{-- Title --}}
                <div class="text-center">
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Two-factor verification</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">We sent a 6-digit code to your email address.</p>
                </div>

                <form method="POST" action="{{ route('mfa.challenge') }}" class="space-y-4">
                    @csrf
                    <x-input
                        name="code"
                        label="Verification code"
                        :required="true"
                        hint="Enter the 6-digit code from your email"
                        autocomplete="one-time-code"
                        inputmode="numeric"
                        maxlength="6"
                        autofocus
                    />
                    <x-button type="submit" class="w-full justify-center">
                        Verify &rarr;
                    </x-button>
                </form>

            </div>
        </x-card>

        {{-- Back link --}}
        <p class="text-center text-sm text-gray-500 dark:text-gray-400">
            <a href="{{ route('login') }}" class="font-medium text-blue-600 hover:text-blue-500">
                &larr; Back to sign in
            </a>
        </p>

    </div>
</x-guest-layout>
