<x-guest-layout title="Set new password">
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
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Set new password</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Choose a strong password for your account.</p>
                </div>

                <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <x-input
                        name="email"
                        label="Email address"
                        type="email"
                        :required="true"
                        value="{{ $email ?? old('email') }}"
                        autocomplete="email"
                    />

                    <x-input
                        name="password"
                        label="New password"
                        type="password"
                        :required="true"
                        hint="At least 12 characters with letters and numbers"
                        autocomplete="new-password"
                    />

                    <x-input
                        name="password_confirmation"
                        label="Confirm new password"
                        type="password"
                        :required="true"
                        autocomplete="new-password"
                    />

                    <x-button type="submit" class="w-full justify-center">
                        Reset password &rarr;
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
