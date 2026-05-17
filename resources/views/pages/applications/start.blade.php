<x-app-layout :title="'Start a new application'">
    <div class="space-y-8">

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Start a new application</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Choose the visa type you'd like to apply for.</p>
            </div>
            <a href="{{ route('dashboard') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                &larr; Back to dashboard
            </a>
        </div>

        @if($visaTypes->isEmpty())
            <x-empty-state
                icon="ti-certificate-off"
                heading="No visa types available"
                description="There are no active visa types at the moment. Please check back later."
            />
        @else
            <form method="POST" action="{{ route('applications.store') }}">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($visaTypes as $type)
                        <label class="cursor-pointer">
                            <input type="radio" name="visa_type_ulid" value="{{ $type->ulid }}" class="sr-only peer" required>
                            <div class="h-full rounded-xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm
                                        transition-colors peer-checked:border-blue-500 peer-checked:bg-blue-50 dark:peer-checked:bg-blue-900/20
                                        hover:border-gray-300 dark:hover:border-gray-600">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/40">
                                        <i class="ti ti-certificate text-xl text-blue-600 dark:text-blue-400"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $type->name }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $type->country->name }} &middot; {{ $type->processing_days }} days processing
                                        </p>
                                        @if($type->description)
                                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 line-clamp-2">{{ $type->description }}</p>
                                        @endif
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs text-gray-600 dark:text-gray-300">
                                                {{ $type->validity_days }} days validity
                                            </span>
                                            <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs text-gray-600 dark:text-gray-300">
                                                {{ ucfirst($type->max_entries) }} entry
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>

                @error('visa_type_ulid')
                    <p class="mt-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <div class="mt-6 flex justify-end">
                    <x-button type="submit" class="px-8">
                        Continue &rarr;
                    </x-button>
                </div>
            </form>
        @endif

    </div>
</x-app-layout>
