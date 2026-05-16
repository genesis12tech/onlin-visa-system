<div class="max-w-2xl mx-auto space-y-8">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Complete your profile</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">We need a few details before you can apply for a visa.</p>
    </div>

    <x-step-indicator :steps="['Personal details', 'Passport & contact']" :current="$currentStep" />

    @if($saved)
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 2000)"
            x-show="show"
            class="flex items-center gap-2 text-sm text-green-600 dark:text-green-400"
        >
            <i class="ti ti-circle-check"></i> Saved
        </div>
    @endif

    @if($currentStep === 1)
        <x-card title="Personal details">
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input name="firstName" label="First name" :required="true"
                        wire:model="firstName" />
                    <x-input name="lastName" label="Last name" :required="true"
                        wire:model="lastName" />
                </div>

                <x-input name="middleName" label="Middle name"
                    wire:model="middleName" hint="Optional" />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input name="dateOfBirth" label="Date of birth" type="date" :required="true"
                        wire:model="dateOfBirth" />

                    <x-select name="gender" label="Gender" :required="true" wire:model="gender">
                        <option value="">Select gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </x-select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-select name="nationalityId" label="Nationality" :required="true" wire:model="nationalityId">
                        <option value="">Select nationality</option>
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}">{{ $country->name }}</option>
                        @endforeach
                    </x-select>

                    <x-select name="countryOfResidenceId" label="Country of residence" :required="true" wire:model="countryOfResidenceId">
                        <option value="">Select country</option>
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}">{{ $country->name }}</option>
                        @endforeach
                    </x-select>
                </div>

                @foreach($errors->all() as $error)
                    <x-alert type="error" :dismissible="false">{{ $error }}</x-alert>
                @endforeach

                <div class="flex justify-end">
                    <x-button wire:click="nextStep" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="nextStep">Next step</span>
                        <span wire:loading wire:target="nextStep">Saving…</span>
                    </x-button>
                </div>
            </div>
        </x-card>
    @endif

    @if($currentStep === 2)
        <x-card title="Passport & contact details">
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input name="passportNumber" label="Passport number" :required="true"
                        wire:model="passportNumber" autocomplete="off" />
                    <x-input name="passportExpiryDate" label="Passport expiry date" type="date" :required="true"
                        wire:model="passportExpiryDate" />
                </div>

                <x-input name="phone" label="Phone number" :required="true"
                    wire:model="phone" hint="Include country code, e.g. +44 7911 000000" />

                <x-input name="addressLine1" label="Address line 1" :required="true"
                    wire:model="addressLine1" />
                <x-input name="addressLine2" label="Address line 2"
                    wire:model="addressLine2" />

                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <x-input name="city" label="City" :required="true"
                        wire:model="city" class="col-span-2 md:col-span-1" />
                    <x-input name="state" label="State / Province"
                        wire:model="state" />
                    <x-input name="postalCode" label="Postal code"
                        wire:model="postalCode" />
                </div>

                @foreach($errors->all() as $error)
                    <x-alert type="error" :dismissible="false">{{ $error }}</x-alert>
                @endforeach

                <div class="flex items-center justify-between">
                    <x-button variant="secondary" wire:click="$set('currentStep', 1)">Back</x-button>
                    <x-button wire:click="complete" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="complete">Complete profile</span>
                        <span wire:loading wire:target="complete">Saving…</span>
                    </x-button>
                </div>
            </div>
        </x-card>
    @endif
</div>
