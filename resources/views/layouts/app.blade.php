<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}{{ isset($title) ? ' — '.$title : '' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
    <nav class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center gap-8">
                    <a href="{{ route('dashboard') }}" class="font-semibold text-lg text-gray-900 dark:text-white">
                        {{ config('app.name') }}
                    </a>
                    <div class="hidden sm:flex items-center gap-6">
                        <a href="{{ route('dashboard') }}"
                           class="text-sm transition-colors {{ request()->routeIs('dashboard') ? 'font-medium text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                            My applications
                        </a>
                        <a href="{{ route('documents') }}"
                           class="text-sm transition-colors {{ request()->routeIs('documents') ? 'font-medium text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                            Documents
                        </a>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <livewire:dashboard.notification-bell />
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-sm font-semibold select-none"
                         title="{{ auth()->user()->name }}">
                        {{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>
    <x-flash />
    <main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        {{ $slot }}
    </main>
    @livewireScripts
</body>
</html>
