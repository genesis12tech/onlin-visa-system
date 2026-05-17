<x-guest-layout title="Verify your email">
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
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Verify your email address</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Thanks for registering! Click the link we sent to your inbox to continue.
                    </p>
                </div>

                @if(session('success'))
                    <x-alert type="success">{{ session('success') }}</x-alert>
                @endif

                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <x-button type="submit" variant="secondary" class="w-full justify-center">
                        Resend verification email
                    </x-button>
                </form>

            </div>
        </x-card>

        {{-- Sign out link --}}
        <div class="text-center">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                    Sign out
                </button>
            </form>
        </div>

    </div>
</x-guest-layout>
