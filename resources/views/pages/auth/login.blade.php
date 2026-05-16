<x-guest-layout title="Sign in">
    <div class="w-full max-w-md space-y-6">
        <div class="text-center">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Sign in to your account</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Don't have an account?
                <a href="{{ route('register') }}" class="font-medium text-blue-600 hover:text-blue-500">Register</a>
            </p>
        </div>

        <x-card>
            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <x-input name="email" label="Email address" type="email" :required="true" value="{{ old('email') }}" autocomplete="email" autofocus />
                <x-input name="password" label="Password" type="password" :required="true" autocomplete="current-password" />

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                        <input type="checkbox" name="remember" class="rounded border-gray-300"> Remember me
                    </label>
                    <a href="{{ route('password.request') }}" class="text-sm text-blue-600 hover:text-blue-500">Forgot password?</a>
                </div>

                <x-button type="submit" class="w-full justify-center">Sign in</x-button>
            </form>
        </x-card>
    </div>
</x-guest-layout>
