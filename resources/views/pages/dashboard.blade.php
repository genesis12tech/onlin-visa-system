<x-app-layout title="My Dashboard">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                Welcome, {{ auth()->user()->applicantProfile->first_name ?? auth()->user()->name }}
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage your visa applications from here.</p>
        </div>

        <x-empty-state
            icon="ti-file-certificate"
            heading="No applications yet"
            description="When you start a visa application, it will appear here. You can track its progress and manage your documents."
        >
            <x-slot name="cta">
                {{-- Application wizard link added in M2 --}}
                <span class="text-sm text-gray-500 dark:text-gray-400">Application wizard coming soon.</span>
            </x-slot>
        </x-empty-state>
    </div>
</x-app-layout>
