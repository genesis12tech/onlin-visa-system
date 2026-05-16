<x-guest-layout title="Create your account">
    <div class="w-full max-w-md space-y-6">
        <div class="text-center">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create your account</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Already have an account?
                <a href="{{ route('login') }}" class="font-medium text-blue-600 hover:text-blue-500">Sign in</a>
            </p>
        </div>

        <x-card>
            <form method="POST" action="{{ route('register') }}" class="space-y-4">
                @csrf

                <x-input name="name" label="Full name" :required="true" value="{{ old('name') }}" autocomplete="name" />
                <x-input name="email" label="Email address" type="email" :required="true" value="{{ old('email') }}" autocomplete="email" />
                <x-input name="password" label="Password" type="password" :required="true"
                    hint="At least 10 characters with letters and numbers" autocomplete="new-password" />
                <x-input name="password_confirmation" label="Confirm password" type="password" :required="true" autocomplete="new-password" />

                <x-button type="submit" class="w-full justify-center">Create account</x-button>
            </form>
        </x-card>
    </div>
</x-guest-layout>
