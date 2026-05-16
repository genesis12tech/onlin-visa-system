<x-guest-layout title="Set new password">
    <div class="w-full max-w-md space-y-6">
        <div class="text-center">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Set new password</h1>
        </div>
        <x-card>
            <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <x-input name="email" label="Email address" type="email" :required="true" value="{{ $email ?? old('email') }}" autocomplete="email" />
                <x-input name="password" label="New password" type="password" :required="true"
                    hint="At least 10 characters with letters and numbers" autocomplete="new-password" />
                <x-input name="password_confirmation" label="Confirm new password" type="password" :required="true" autocomplete="new-password" />
                <x-button type="submit" class="w-full justify-center">Reset password</x-button>
            </form>
        </x-card>
    </div>
</x-guest-layout>
