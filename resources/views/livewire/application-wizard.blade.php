<div class="max-w-3xl mx-auto space-y-6 py-8 px-4 sm:px-6 lg:px-8">

    {{-- Success state --}}
    @if ($submitted)
        <x-card>
            <div class="py-6 text-center space-y-4">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-teal-100 dark:bg-teal-900/40">
                    <i class="ti ti-circle-check text-3xl text-teal-600 dark:text-teal-400"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Application Submitted!</h2>
                <p class="text-gray-500 dark:text-gray-400">
                    Your application has been submitted and is under review.
                </p>
                @if ($trackingNumber)
                    <div class="inline-flex items-center gap-2 rounded-full bg-teal-50 dark:bg-teal-900/20 border border-teal-200 dark:border-teal-800 px-4 py-2">
                        <i class="ti ti-hash text-teal-600 dark:text-teal-400 text-sm"></i>
                        <span class="font-mono text-sm font-semibold text-teal-700 dark:text-teal-300">{{ $trackingNumber }}</span>
                    </div>
                @endif
                <div class="pt-2">
                    <x-button tag="a" href="{{ route('applications.index') }}">
                        View My Applications
                    </x-button>
                </div>
            </div>
        </x-card>

    @else

        {{-- Step indicator --}}
        <x-step-indicator
            :steps="['Visa Type', 'Personal Info', 'Travel Details', 'Documents', 'Declaration']"
            :current="$currentStep"
            color="teal"
        />

        {{-- Step 1: Visa Type --}}
        @if ($currentStep === 1)
            <x-card title="Select Visa Type">
                <x-slot:footer>
                    <div class="flex justify-end">
                        <x-button variant="teal" wire:click="nextStep">
                            Continue <i class="ti ti-arrow-right text-sm"></i>
                        </x-button>
                    </div>
                </x-slot:footer>

                <div class="space-y-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Choose the visa category that matches your travel purpose.</p>

                    @error('selectedVisaTypeUlid')
                        <x-alert type="error" :dismissible="false">{{ $message }}</x-alert>
                    @enderror

                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ($visaTypes as $type)
                            <x-visa-type-card
                                :type="$type"
                                :selected="$selectedVisaTypeUlid === $type->ulid"
                                wire:click="$set('selectedVisaTypeUlid', '{{ $type->ulid }}')"
                            />
                        @endforeach
                    </div>
                </div>
            </x-card>
        @endif

        {{-- Step 2: Personal Information --}}
        @if ($currentStep === 2)
            <x-card title="Personal Information">
                <x-slot:footer>
                    <div class="flex items-center justify-between">
                        <x-button variant="secondary" wire:click="previousStep">
                            <i class="ti ti-arrow-left text-sm"></i> Back
                        </x-button>
                        <x-button variant="teal" wire:click="nextStep">
                            Continue <i class="ti ti-arrow-right text-sm"></i>
                        </x-button>
                    </div>
                </x-slot:footer>

                <div class="space-y-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Enter your personal and passport details.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-input name="firstName" label="First Name" :required="true" wire:model="firstName" />
                        <x-input name="lastName" label="Last Name" :required="true" wire:model="lastName" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-input name="dateOfBirth" label="Date of Birth" type="date" :required="true" wire:model="dateOfBirth" />
                        <x-select name="gender" label="Gender" :required="true" wire:model="gender">
                            <option value="">Select gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </x-select>
                    </div>

                    <x-select name="nationalityId" label="Nationality" :required="true" wire:model="nationalityId">
                        <option value="">Select country</option>
                        @foreach ($countries as $country)
                            <option value="{{ $country->id }}">{{ $country->name }}</option>
                        @endforeach
                    </x-select>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-input name="passportNumber" label="Passport Number" :required="true" wire:model="passportNumber" autocomplete="off" />
                        <x-input name="passportExpiry" label="Passport Expiry Date" type="date" :required="true" wire:model="passportExpiry" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-input name="phone" label="Phone" type="tel" :required="true" wire:model="phone" />
                        <x-input name="email" label="Email" type="email" :required="true" wire:model="email" />
                    </div>
                </div>
            </x-card>
        @endif

        {{-- Step 3: Travel Details --}}
        @if ($currentStep === 3)
            <x-card title="Travel Details">
                <x-slot:footer>
                    <div class="flex items-center justify-between">
                        <x-button variant="secondary" wire:click="previousStep">
                            <i class="ti ti-arrow-left text-sm"></i> Back
                        </x-button>
                        <x-button variant="teal" wire:click="nextStep">
                            Continue <i class="ti ti-arrow-right text-sm"></i>
                        </x-button>
                    </div>
                </x-slot:footer>

                <div class="space-y-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Provide your travel and accommodation information.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-input name="arrivalDate" label="Arrival Date" type="date" :required="true" wire:model="arrivalDate" />
                        <x-input name="departureDate" label="Departure Date" type="date" :required="true" wire:model="departureDate" />
                    </div>

                    <x-select name="portOfEntry" label="Port of Entry" :required="true" wire:model="portOfEntry">
                        <option value="">Select a port of entry</option>
                        @foreach ($serviceLocations as $location)
                            <option value="{{ $location->name }}">{{ $location->name }}</option>
                        @endforeach
                    </x-select>

                    <x-input name="accommodation" label="Accommodation" :required="true" wire:model="accommodation"
                        placeholder="Hotel name and address" />

                    <x-textarea name="purpose" label="Purpose of Visit" :required="true" :rows="4" wire:model="purpose"
                        placeholder="Please describe your purpose of visit (minimum 20 characters)" />

                    <x-select name="previouslyRefused" label="Previously refused a visa?" :required="true" wire:model="previouslyRefused">
                        <option value="">Select an option</option>
                        <option value="no">No</option>
                        <option value="yes">Yes</option>
                    </x-select>
                </div>
            </x-card>
        @endif

        {{-- Step 4: Documents --}}
        @if ($currentStep === 4)
            <x-card title="Supporting Documents">
                <x-slot:footer>
                    <div class="flex items-center justify-between">
                        <x-button variant="secondary" wire:click="previousStep">
                            <i class="ti ti-arrow-left text-sm"></i> Back
                        </x-button>
                        <x-button variant="teal" wire:click="nextStep">
                            Continue <i class="ti ti-arrow-right text-sm"></i>
                        </x-button>
                    </div>
                </x-slot:footer>

                @if ($application)
                    @livewire('applications.document-upload-panel', ['applicationUlid' => $applicationUlid], key('doc-upload-' . $applicationUlid))
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">No application found. Please go back and complete earlier steps.</p>
                @endif
            </x-card>
        @endif

        {{-- Step 5: Declaration --}}
        @if ($currentStep === 5)
            <x-card title="Declaration">
                <x-slot:footer>
                    <div class="flex items-center justify-between">
                        <x-button variant="secondary" wire:click="previousStep">
                            <i class="ti ti-arrow-left text-sm"></i> Back
                        </x-button>
                        <x-button variant="teal" wire:click="submit">
                            <i class="ti ti-send text-sm"></i> Submit Application
                        </x-button>
                    </div>
                </x-slot:footer>

                <div class="space-y-6">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Please review and confirm the declaration before submitting.</p>

                    <x-alert type="warning" :dismissible="false">
                        <p class="font-semibold mb-1">I, the undersigned, declare that:</p>
                        <ul class="list-disc pl-4 space-y-0.5 text-xs">
                            <li>The information provided in this application is true, complete, and correct.</li>
                            <li>I understand that providing false information may result in rejection of my application.</li>
                            <li>I consent to the processing of my personal data for the purposes of this visa application.</li>
                            <li>I will comply with all conditions imposed on any visa granted.</li>
                        </ul>
                    </x-alert>

                    @error('submit')
                        <x-alert type="error" :dismissible="false">{{ $message }}</x-alert>
                    @enderror

                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox"
                               wire:model="declarationAccepted"
                               class="mt-0.5 h-4 w-4 rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            I confirm that I have read, understood, and agree to the declaration above.
                        </span>
                    </label>
                    @error('declarationAccepted')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </x-card>
        @endif

    @endif
</div>
