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
    <x-flash />
    <main class="min-h-full flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        {{ $slot }}
    </main>
    @livewireScripts
</body>
</html>
