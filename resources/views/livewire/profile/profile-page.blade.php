<div class="max-w-3xl space-y-6">

    {{-- Profile card --}}
    <x-card>
        <div class="flex items-center gap-4">
            <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full text-xl font-bold text-white"
                 style="background:var(--portal-teal)">
                {{ $user->initials() }}
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                        {{ $user->name }}
                    </h2>
                    @if($user->email_verified_at)
                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium
                                     bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                            <i class="ti ti-circle-check text-xs" aria-hidden="true"></i>
                            Verified
                        </span>
                    @endif
                </div>
                <p class="text-sm mt-0.5" style="color:var(--portal-ink-4)">{{ $user->email }}</p>
            </div>
        </div>
    </x-card>

    {{-- Personal details form --}}
    <x-card title="Personal Details">
        <form wire:submit="saveProfile" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-input name="firstName" label="First name" :required="true"
                    wire:model="firstName" />
                <x-input name="lastName" label="Last name" :required="true"
                    wire:model="lastName" />
                <x-input name="middleName" label="Middle name"
                    wire:model="middleName" hint="Optional" />
                <x-input name="dateOfBirth" label="Date of birth" type="date" :required="true"
                    wire:model="dateOfBirth" />
                <x-select name="gender" label="Gender" :required="true" wire:model="gender">
                    <option value="">Select…</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="other">Other</option>
                </x-select>
                <x-input name="phone" label="Phone" :required="true"
                    type="tel" wire:model="phone" />
                <x-select name="nationalityId" label="Nationality" :required="true" wire:model="nationalityId">
                    <option value="">Select…</option>
                    @foreach($countries as $country)
                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                    @endforeach
                </x-select>
                <x-select name="countryOfResidenceId" label="Country of residence" :required="true" wire:model="countryOfResidenceId">
                    <option value="">Select…</option>
                    @foreach($countries as $country)
                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                    @endforeach
                </x-select>
                <x-input name="passportNumber" label="Passport number" :required="true"
                    wire:model="passportNumber" autocomplete="off" />
                <x-input name="passportExpiryDate" label="Passport expiry" type="date" :required="true"
                    wire:model="passportExpiryDate" />
            </div>

            <div class="space-y-4 border-t border-gray-100 dark:border-gray-700 pt-4">
                <x-input name="addressLine1" label="Address line 1" :required="true"
                    wire:model="addressLine1" />
                <x-input name="addressLine2" label="Address line 2"
                    wire:model="addressLine2" />
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <x-input name="city" label="City" :required="true"
                        wire:model="city" />
                    <x-input name="state" label="State / Region"
                        wire:model="state" />
                    <x-input name="postalCode" label="Postal code"
                        wire:model="postalCode" />
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <x-button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove>Save changes</span>
                    <span wire:loading>Saving…</span>
                </x-button>
            </div>
        </form>
    </x-card>

    {{-- Change password --}}
    <x-card title="Change Password">
        <form wire:submit="changePassword" class="space-y-4">
            <x-input name="currentPassword" label="Current password" type="password"
                wire:model="currentPassword" autocomplete="current-password" />
            <x-input name="newPassword" label="New password" type="password"
                wire:model="newPassword" autocomplete="new-password" />
            <x-input name="newPasswordConfirmation" label="Confirm new password" type="password"
                wire:model="newPasswordConfirmation" autocomplete="new-password" />
            <div class="flex justify-end">
                <x-button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove>Update password</span>
                    <span wire:loading>Updating…</span>
                </x-button>
            </div>
        </form>
    </x-card>

    {{-- Notification preferences --}}
    <x-card title="Notification Preferences">
        <div class="space-y-4">
            @foreach([
                ['notifyEmailApplicationUpdates', 'Application status updates', 'Receive emails when your application status changes.'],
                ['notifyEmailPaymentReceipts', 'Payment receipts', 'Receive email receipts for completed payments.'],
                ['notifyEmailDocumentReminders', 'Document reminders', 'Receive reminders when documents are needed.'],
            ] as [$property, $label, $description])
                <label class="flex items-start gap-3 cursor-pointer">
                    <div class="relative flex-shrink-0 mt-0.5">
                        <input type="checkbox"
                               wire:model.live="{{ $property }}"
                               wire:change="saveNotificationPreferences"
                               class="sr-only peer">
                        <div class="w-9 h-5 rounded-full transition-colors peer-checked:bg-teal-600 bg-gray-200 dark:bg-gray-700"></div>
                        <div class="absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white shadow transition-transform peer-checked:translate-x-4"></div>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $label }}</p>
                        <p class="text-xs mt-0.5" style="color:var(--portal-ink-4)">{{ $description }}</p>
                    </div>
                </label>
            @endforeach
        </div>
    </x-card>

</div>
