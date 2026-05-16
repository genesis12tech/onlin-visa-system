<x-guest-layout title="Verify your email">
    <div class="w-full max-w-md space-y-6">
        <div class="text-center">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Verify your email address</h1>
        </div>
        <x-card>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                Thanks for registering! Before you continue, please verify your email address by clicking the link we just sent you.
            </p>

            @if(session('success'))
                <x-alert type="success" class="mb-4">{{ session('success') }}</x-alert>
            @endif

            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <x-button type="submit" variant="secondary" class="w-full justify-center">Resend verification email</x-button>
            </form>

            <div class="mt-4 text-center">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Sign out</button>
                </form>
            </div>
        </x-card>
    </div>
</x-guest-layout>
