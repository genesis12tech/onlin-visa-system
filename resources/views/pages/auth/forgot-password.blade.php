<x-guest-layout title="Forgot your password?">
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
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Forgot your password?</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Enter your email and we'll send you a reset link.</p>
                </div>

                @if(session('success'))
                    <x-alert type="success">{{ session('success') }}</x-alert>
                @endif

                <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                    @csrf
                    <x-input
                        name="email"
                        label="Email address"
                        type="email"
                        :required="true"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        autofocus
                    />
                    <x-button type="submit" class="w-full justify-center">
                        Send reset link &rarr;
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
