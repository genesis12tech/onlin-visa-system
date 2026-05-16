<x-guest-layout title="Reset your password">
    <div class="w-full max-w-md space-y-6">
        <div class="text-center">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Forgot your password?</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Enter your email and we'll send you a reset link.</p>
        </div>
        <x-card>
            @if(session('success'))
                <x-alert type="success" class="mb-4">{{ session('success') }}</x-alert>
            @endif
            <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                @csrf
                <x-input name="email" label="Email address" type="email" :required="true" value="{{ old('email') }}" autofocus />
                <x-button type="submit" class="w-full justify-center">Send reset link</x-button>
            </form>
            <div class="mt-4 text-center">
                <a href="{{ route('login') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Back to sign in</a>
            </div>
        </x-card>
    </div>
</x-guest-layout>
