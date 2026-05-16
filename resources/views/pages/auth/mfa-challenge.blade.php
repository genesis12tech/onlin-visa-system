<x-guest-layout title="Two-factor verification">
    <div class="w-full max-w-md space-y-6">
        <div class="text-center">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Two-factor verification</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">We sent a 6-digit code to your email address.</p>
        </div>
        <x-card>
            <form method="POST" action="{{ route('mfa.challenge') }}" class="space-y-4">
                @csrf
                <x-input name="code" label="Verification code" :required="true"
                    hint="Enter the 6-digit code from your email"
                    autocomplete="one-time-code"
                    inputmode="numeric"
                    maxlength="6"
                    autofocus />
                <x-button type="submit" class="w-full justify-center">Verify</x-button>
            </form>
            <div class="mt-4 text-center">
                <a href="{{ route('login') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Back to sign in</a>
            </div>
        </x-card>
    </div>
</x-guest-layout>
