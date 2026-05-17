# Milestone 1 — Registration & Identity Flow

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the complete applicant-facing registration, login, MFA, email verification, profile wizard, and dashboard shell so a new user can sign up and reach an empty application dashboard.

**Architecture:** Custom Laravel auth (no Fortify/Breeze) with plain Blade + Livewire 3 for the applicant portal. MFA is email OTP stored in cache. The profile wizard is a two-step Livewire full-page component. All auth business logic lives in `app/Domain/Identity/Actions/`. Filament panels are unaffected.

**Tech Stack:** Laravel 12, Livewire 3, Tailwind CSS 4, Tabler Icons (webfont via npm), PHPUnit 11, Spatie Permission.

---

## What already exists — do NOT recreate

- `app/Domain/Identity/Models/ApplicantProfile.php` — model with encrypted casts
- `app/Domain/Identity/Models/Country.php`
- `app/Policies/ApplicantProfilePolicy.php` — already registered in AppServiceProvider
- All migrations (users, applicant_profiles, countries, etc.)
- `database/factories/UserFactory.php`
- `app/Providers/AppServiceProvider.php` — has policy + rate limiter registration
- `app/Providers/Filament/AdminPanelProvider.php`

---

## File Map

**Create:**
- `resources/css/app.css` — update: Tabler webfont import
- `resources/js/app.js` — update: keep minimal
- `resources/views/layouts/guest.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/components/button.blade.php` + `app/View/Components/Button.php`
- `resources/views/components/input.blade.php` + `app/View/Components/Input.php`
- `resources/views/components/select.blade.php` + `app/View/Components/Select.php`
- `resources/views/components/textarea.blade.php` + `app/View/Components/Textarea.php`
- `resources/views/components/alert.blade.php` + `app/View/Components/Alert.php`
- `resources/views/components/badge.blade.php` + `app/View/Components/Badge.php`
- `resources/views/components/card.blade.php` + `app/View/Components/Card.php`
- `resources/views/components/step-indicator.blade.php` + `app/View/Components/StepIndicator.php`
- `resources/views/components/empty-state.blade.php` + `app/View/Components/EmptyState.php`
- `resources/views/components/status-timeline.blade.php` + `app/View/Components/StatusTimeline.php`
- `resources/views/components/flash.blade.php`
- `resources/views/components/modal.blade.php` + `app/View/Components/Modal.php`
- `database/migrations/YYYY_MM_DD_add_two_factor_to_users_table.php`
- `database/factories/ApplicantProfileFactory.php`
- `app/Domain/Identity/Data/ApplicantProfileData.php`
- `app/Domain/Identity/Actions/RegisterApplicant.php`
- `app/Domain/Identity/Actions/CompleteApplicantProfile.php`
- `app/Http/Requests/Auth/RegisterRequest.php`
- `app/Http/Requests/Auth/LoginRequest.php`
- `app/Http/Controllers/Auth/RegisteredUserController.php`
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `app/Http/Controllers/Auth/EmailVerificationController.php`
- `app/Http/Controllers/Auth/PasswordResetLinkController.php`
- `app/Http/Controllers/Auth/NewPasswordController.php`
- `app/Http/Controllers/Auth/MfaChallengeController.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Middleware/EnsureProfileComplete.php`
- `app/Livewire/Profile/SetupWizard.php`
- `resources/views/pages/auth/register.blade.php`
- `resources/views/pages/auth/login.blade.php`
- `resources/views/pages/auth/verify-email.blade.php`
- `resources/views/pages/auth/forgot-password.blade.php`
- `resources/views/pages/auth/reset-password.blade.php`
- `resources/views/pages/auth/mfa-challenge.blade.php`
- `resources/views/pages/dashboard.blade.php`
- `resources/views/livewire/profile/setup-wizard.blade.php`
- `tests/Feature/RegistrationTest.php`
- `tests/Feature/LoginTest.php`
- `tests/Feature/EmailVerificationTest.php`
- `tests/Feature/PasswordResetTest.php`
- `tests/Feature/MfaChallengeTest.php`
- `tests/Feature/ProfileWizardTest.php`
- `tests/Feature/DashboardTest.php`

**Modify:**
- `resources/css/app.css` — add Tabler webfont import
- `app/Models/User.php` — add MustVerifyEmail, applicantProfile() relationship
- `app/Providers/AppServiceProvider.php` — add login + mfa-otp rate limiters
- `bootstrap/app.php` — fix guest redirect to `route('login')`
- `routes/web.php` — add all applicant portal routes

---

## Task 1: Install Tabler Icons + update CSS/JS

**Files:**
- Modify: `resources/css/app.css`
- Modify: `resources/js/app.js`

- [ ] **Step 1: Install the webfont package**

```bash
cd /path/to/project && npm install @tabler/icons-webfont
```

- [ ] **Step 2: Add the webfont import to `resources/css/app.css`**

Replace the entire file with:

```css
@import '@tabler/icons-webfont/dist/tabler-icons.css';
@import 'tailwindcss';

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../**/*.blade.php';
@source '../**/*.js';

@theme {
    --font-sans: ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji',
        'Segoe UI Symbol', 'Noto Color Emoji';
}
```

- [ ] **Step 3: Verify build passes**

```bash
npm run build
```

Expected: zero errors, `public/build/manifest.json` updated.

- [ ] **Step 4: Commit**

```bash
git add resources/css/app.css package.json package-lock.json
git commit -m "feat(m1): install Tabler Icons webfont via npm"
```

---

## Task 2: Layouts

**Files:**
- Create: `resources/views/layouts/guest.blade.php`
- Create: `resources/views/layouts/app.blade.php`

- [ ] **Step 1: Create `resources/views/layouts/guest.blade.php`**

```blade
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
```

- [ ] **Step 2: Create `resources/views/layouts/app.blade.php`**

```blade
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
                <a href="{{ route('dashboard') }}" class="font-semibold text-lg text-gray-900 dark:text-white">
                    {{ config('app.name') }}
                </a>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ auth()->user()->name }}</span>
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
```

- [ ] **Step 3: Build to verify layouts compile**

```bash
npm run build
```

- [ ] **Step 4: Commit**

```bash
git add resources/views/layouts/
git commit -m "feat(m1): add guest and app layouts"
```

---

## Task 3: x-* Blade Component Library

**Files:** `app/View/Components/*.php` + `resources/views/components/*.blade.php`

Components are class-backed so they can be type-hinted. Create them in this order.

- [ ] **Step 1: Create `app/View/Components/Button.php`**

```php
<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class Button extends Component
{
    public function __construct(
        public string $variant = 'primary',
        public string $type = 'button',
        public bool $loading = false,
    ) {}

    public function render(): View
    {
        return view('components.button');
    }
}
```

- [ ] **Step 2: Create `resources/views/components/button.blade.php`**

```blade
@props(['variant' => 'primary', 'type' => 'button', 'loading' => false])

@php
$classes = match($variant) {
    'primary'   => 'bg-blue-600 hover:bg-blue-700 text-white focus:ring-blue-500',
    'secondary' => 'bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 focus:ring-blue-500',
    'danger'    => 'bg-red-600 hover:bg-red-700 text-white focus:ring-red-500',
    default     => 'bg-blue-600 hover:bg-blue-700 text-white focus:ring-blue-500',
};
@endphp

<button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => "inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors $classes"]) }}
    @if($loading) disabled @endif
>
    @if($loading)
        <i class="ti ti-loader-2 animate-spin text-base"></i>
    @endif
    {{ $slot }}
</button>
```

- [ ] **Step 3: Create `app/View/Components/Input.php`**

```php
<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class Input extends Component
{
    public function __construct(
        public string $name,
        public string $label = '',
        public bool $required = false,
        public string $hint = '',
        public string $type = 'text',
    ) {}

    public function render(): View
    {
        return view('components.input');
    }
}
```

- [ ] **Step 4: Create `resources/views/components/input.blade.php`**

```blade
@props(['name', 'label' => '', 'required' => false, 'hint' => '', 'type' => 'text'])

<div>
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $label }}
            @if($required)
                <span class="text-red-500 ml-0.5">*</span>
            @endif
        </label>
    @endif
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        {{ $attributes->merge(['class' => 'block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent' . ($errors->has($name) ? ' border-red-500 focus:ring-red-500' : '')]) }}
    >
    @if($hint && !$errors->has($name))
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
```

- [ ] **Step 5: Create `app/View/Components/Select.php`**

```php
<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class Select extends Component
{
    public function __construct(
        public string $name,
        public string $label = '',
        public bool $required = false,
    ) {}

    public function render(): View
    {
        return view('components.select');
    }
}
```

- [ ] **Step 6: Create `resources/views/components/select.blade.php`**

```blade
@props(['name', 'label' => '', 'required' => false])

<div>
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $label }}
            @if($required)
                <span class="text-red-500 ml-0.5">*</span>
            @endif
        </label>
    @endif
    <select
        id="{{ $name }}"
        name="{{ $name }}"
        {{ $attributes->merge(['class' => 'block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent' . ($errors->has($name) ? ' border-red-500' : '')]) }}
    >
        {{ $slot }}
    </select>
    @error($name)
        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
```

- [ ] **Step 7: Create `app/View/Components/Textarea.php`**

```php
<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class Textarea extends Component
{
    public function __construct(
        public string $name,
        public string $label = '',
        public bool $required = false,
        public int $rows = 4,
    ) {}

    public function render(): View
    {
        return view('components.textarea');
    }
}
```

- [ ] **Step 8: Create `resources/views/components/textarea.blade.php`**

```blade
@props(['name', 'label' => '', 'required' => false, 'rows' => 4])

<div>
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $label }}
            @if($required)<span class="text-red-500 ml-0.5">*</span>@endif
        </label>
    @endif
    <textarea
        id="{{ $name }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        {{ $attributes->merge(['class' => 'block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 resize-y focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent' . ($errors->has($name) ? ' border-red-500' : '')]) }}
    >{{ $slot }}</textarea>
    @error($name)
        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
```

- [ ] **Step 9: Create `app/View/Components/Alert.php`**

```php
<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class Alert extends Component
{
    public function __construct(
        public string $type = 'info',
        public bool $dismissible = true,
    ) {}

    public function render(): View
    {
        return view('components.alert');
    }
}
```

- [ ] **Step 10: Create `resources/views/components/alert.blade.php`**

```blade
@props(['type' => 'info', 'dismissible' => true])

@php
[$bg, $border, $text, $icon] = match($type) {
    'success' => ['bg-green-50 dark:bg-green-900/20',  'border-green-200 dark:border-green-800', 'text-green-800 dark:text-green-200', 'ti-circle-check'],
    'error'   => ['bg-red-50 dark:bg-red-900/20',    'border-red-200 dark:border-red-800',   'text-red-800 dark:text-red-200',   'ti-circle-x'],
    'warning' => ['bg-amber-50 dark:bg-amber-900/20', 'border-amber-200 dark:border-amber-800','text-amber-800 dark:text-amber-200','ti-alert-triangle'],
    default   => ['bg-blue-50 dark:bg-blue-900/20',   'border-blue-200 dark:border-blue-800', 'text-blue-800 dark:text-blue-200', 'ti-info-circle'],
};
@endphp

<div
    x-data="{ open: true }"
    x-show="open"
    class="flex items-start gap-3 rounded-lg border p-4 {{ $bg }} {{ $border }} {{ $text }}"
    role="alert"
>
    <i class="ti {{ $icon }} text-lg flex-shrink-0 mt-0.5"></i>
    <div class="flex-1 text-sm">{{ $slot }}</div>
    @if($dismissible)
        <button @click="open = false" class="flex-shrink-0 hover:opacity-75" aria-label="Dismiss">
            <i class="ti ti-x text-base"></i>
        </button>
    @endif
</div>
```

- [ ] **Step 11: Create `app/View/Components/Badge.php`**

```php
<?php

namespace App\View\Components;

use App\Domain\Applications\Enums\ApplicationStatus;
use Illuminate\View\Component;
use Illuminate\View\View;

class Badge extends Component
{
    public string $colorClasses;

    public function __construct(
        public ?ApplicationStatus $status = null,
        public string $color = 'gray',
    ) {
        $resolvedColor = $status ? $this->statusColor($status) : $color;

        $this->colorClasses = match ($resolvedColor) {
            'green'  => 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200',
            'blue'   => 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200',
            'purple' => 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-200',
            'amber'  => 'bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200',
            'red'    => 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200',
            default  => 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300',
        };
    }

    private function statusColor(ApplicationStatus $status): string
    {
        return match ($status) {
            ApplicationStatus::Draft, ApplicationStatus::Withdrawn                                    => 'gray',
            ApplicationStatus::Submitted, ApplicationStatus::PaymentPending                           => 'blue',
            ApplicationStatus::PaymentCompleted, ApplicationStatus::UnderReview,
            ApplicationStatus::DocsRequired                                                           => 'purple',
            ApplicationStatus::AdditionalInfoRequested                                                => 'amber',
            ApplicationStatus::Approved                                                               => 'green',
            ApplicationStatus::Rejected                                                               => 'red',
        };
    }

    public function render(): View
    {
        return view('components.badge');
    }
}
```

- [ ] **Step 12: Create `resources/views/components/badge.blade.php`**

```blade
<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium $colorClasses"]) }}>
    {{ $status?->label() ?? $slot }}
</span>
```

- [ ] **Step 13: Create `app/View/Components/Card.php`**

```php
<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class Card extends Component
{
    public function __construct(
        public string $title = '',
    ) {}

    public function render(): View
    {
        return view('components.card');
    }
}
```

- [ ] **Step 14: Create `resources/views/components/card.blade.php`**

```blade
@props(['title' => ''])

<div {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm']) }}>
    @if($title)
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $title }}</h3>
        </div>
    @endif
    <div class="px-6 py-4">
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="px-6 py-3 bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-700 rounded-b-xl">
            {{ $footer }}
        </div>
    @endisset
</div>
```

- [ ] **Step 15: Create `app/View/Components/StepIndicator.php`**

```php
<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class StepIndicator extends Component
{
    public function __construct(
        /** @var array<int, string> $steps */
        public array $steps,
        public int $current,
    ) {}

    public function render(): View
    {
        return view('components.step-indicator');
    }
}
```

- [ ] **Step 16: Create `resources/views/components/step-indicator.blade.php`**

```blade
@props(['steps', 'current'])

<nav aria-label="Progress">
    <ol class="flex items-center overflow-x-auto">
        @foreach($steps as $index => $label)
            @php
                $stepNumber = $index + 1;
                $isDone    = $stepNumber < $current;
                $isActive  = $stepNumber === $current;
            @endphp
            <li class="flex items-center flex-shrink-0 {{ !$loop->last ? 'flex-1' : '' }}">
                <span class="flex items-center gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium flex-shrink-0
                        {{ $isDone   ? 'bg-blue-600 text-white' : '' }}
                        {{ $isActive ? 'border-2 border-blue-600 text-blue-600 dark:text-blue-400' : '' }}
                        {{ !$isDone && !$isActive ? 'border-2 border-gray-300 dark:border-gray-600 text-gray-500' : '' }}">
                        @if($isDone)
                            <i class="ti ti-check text-sm"></i>
                        @else
                            {{ $stepNumber }}
                        @endif
                    </span>
                    <span class="text-sm font-medium
                        {{ $isActive ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400' }}
                        whitespace-nowrap">
                        {{ $label }}
                    </span>
                </span>
                @if(!$loop->last)
                    <div class="flex-1 mx-3 h-px bg-gray-200 dark:bg-gray-700 min-w-[2rem]"></div>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
```

- [ ] **Step 17: Create `app/View/Components/EmptyState.php`**

```php
<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class EmptyState extends Component
{
    public function __construct(
        public string $icon = 'ti-folder-open',
        public string $heading = 'Nothing here yet',
        public string $description = '',
    ) {}

    public function render(): View
    {
        return view('components.empty-state');
    }
}
```

- [ ] **Step 18: Create `resources/views/components/empty-state.blade.php`**

```blade
@props(['icon' => 'ti-folder-open', 'heading' => 'Nothing here yet', 'description' => ''])

<div class="flex flex-col items-center justify-center py-16 text-center">
    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700 mb-4">
        <i class="ti {{ $icon }} text-3xl text-gray-400 dark:text-gray-500"></i>
    </div>
    <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-1">{{ $heading }}</h3>
    @if($description)
        <p class="text-sm text-gray-500 dark:text-gray-400 max-w-sm">{{ $description }}</p>
    @endif
    @isset($cta)
        <div class="mt-6">{{ $cta }}</div>
    @endisset
</div>
```

- [ ] **Step 19: Create `app/View/Components/StatusTimeline.php`**

```php
<?php

namespace App\View\Components;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\Component;
use Illuminate\View\View;

class StatusTimeline extends Component
{
    public function __construct(
        public Collection $histories,
        public bool $publicOnly = false,
    ) {}

    public function render(): View
    {
        return view('components.status-timeline');
    }
}
```

- [ ] **Step 20: Create `resources/views/components/status-timeline.blade.php`**

```blade
@props(['histories', 'publicOnly' => false])

@php
$items = $publicOnly
    ? $histories->whereNotNull('public_label')
    : $histories;
@endphp

@if($items->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400 italic">No status history yet.</p>
@else
    <ol class="relative border-l border-gray-200 dark:border-gray-700 ml-3">
        @foreach($items as $history)
            <li class="mb-6 ml-6">
                <span class="absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900 ring-8 ring-white dark:ring-gray-800">
                    <i class="ti ti-circle-check text-xs text-blue-600 dark:text-blue-300"></i>
                </span>
                <p class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ $publicOnly ? ($history->public_label ?? $history->to_status) : $history->to_status }}
                </p>
                <time class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $history->created_at->format('d M Y, H:i') }}
                </time>
            </li>
        @endforeach
    </ol>
@endif
```

- [ ] **Step 21: Create `resources/views/components/flash.blade.php`** (anonymous — no class needed)

```blade
@if(session('success'))
    <div class="fixed top-4 right-4 z-50 max-w-sm w-full">
        <x-alert type="success">{{ session('success') }}</x-alert>
    </div>
@endif

@if(session('error'))
    <div class="fixed top-4 right-4 z-50 max-w-sm w-full">
        <x-alert type="error">{{ session('error') }}</x-alert>
    </div>
@endif
```

- [ ] **Step 22: Create `app/View/Components/Modal.php`**

```php
<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class Modal extends Component
{
    public function __construct(
        public string $id,
        public string $title = '',
    ) {}

    public function render(): View
    {
        return view('components.modal');
    }
}
```

- [ ] **Step 23: Create `resources/views/components/modal.blade.php`**

```blade
@props(['id', 'title' => ''])

<div
    x-data="{ open: false }"
    x-on:open-modal.window="if ($event.detail.id === '{{ $id }}') open = true"
    x-on:close-modal.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $id }}-title"
>
    <div class="absolute inset-0 bg-black/50" @click="open = false"></div>
    <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-lg w-full mx-4 p-6">
        @if($title)
            <div class="flex items-center justify-between mb-4">
                <h2 id="{{ $id }}-title" class="text-lg font-semibold text-gray-900 dark:text-white">{{ $title }}</h2>
                <button @click="open = false" aria-label="Close modal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="ti ti-x text-xl"></i>
                </button>
            </div>
        @endif
        {{ $slot }}
    </div>
</div>
```

- [ ] **Step 24: Build and verify all components compile**

```bash
npm run build
```

- [ ] **Step 25: Commit**

```bash
git add app/View/Components/ resources/views/components/
git commit -m "feat(m1): add x-* Blade component library (all 12 components)"
```

---

## Task 4: User Model + MFA Migration

**Files:**
- Create: `database/migrations/YYYY_MM_DD_add_two_factor_to_users_table.php`
- Modify: `app/Models/User.php`

- [ ] **Step 1: Create the migration**

```bash
php artisan make:migration add_two_factor_to_users_table --no-interaction
```

Edit the generated file:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('two_factor_enabled')->default(false)->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('two_factor_enabled');
        });
    }
};
```

- [ ] **Step 2: Update `app/Models/User.php`**

```php
<?php

namespace App\Models;

use App\Domain\Identity\Models\ApplicantProfile;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'two_factor_enabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'password'           => 'hashed',
            'two_factor_enabled' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole([
            'super_admin',
            'admin',
            'case_officer',
            'senior_officer',
            'document_verifier',
            'finance_officer',
            'support_staff',
        ]);
    }

    public function applicantProfile(): HasOne
    {
        return $this->hasOne(ApplicantProfile::class);
    }
}
```

- [ ] **Step 3: Run migration**

```bash
php artisan migrate --no-interaction
```

- [ ] **Step 4: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 5: Commit**

```bash
git add database/migrations/ app/Models/User.php
git commit -m "feat(m1): add two_factor_enabled column, MustVerifyEmail, applicantProfile relationship"
```

---

## Task 5: ApplicantProfile Factory

**Files:**
- Create: `database/factories/ApplicantProfileFactory.php`

- [ ] **Step 1: Create the factory**

```php
<?php

namespace Database\Factories;

use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicantProfile>
 */
class ApplicantProfileFactory extends Factory
{
    protected $model = ApplicantProfile::class;

    public function definition(): array
    {
        return [
            'user_id'                  => User::factory(),
            'first_name'               => fake()->firstName(),
            'last_name'                => fake()->lastName(),
            'middle_name'              => fake()->optional(0.3)->firstName(),
            'date_of_birth'            => fake()->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
            'gender'                   => fake()->randomElement(['male', 'female', 'other']),
            'nationality_id'           => Country::factory(),
            'country_of_residence_id'  => Country::factory(),
            'passport_number'          => strtoupper(fake()->bothify('??#######')),
            'passport_expiry_date'     => fake()->dateTimeBetween('+1 year', '+10 years')->format('Y-m-d'),
            'phone'                    => fake()->phoneNumber(),
            'address_line_1'           => fake()->streetAddress(),
            'address_line_2'           => fake()->optional(0.3)->secondaryAddress(),
            'city'                     => fake()->city(),
            'state'                    => fake()->optional(0.7)->state(),
            'postal_code'              => fake()->optional(0.7)->postcode(),
        ];
    }
}
```

- [ ] **Step 2: Add a Country factory** (Country doesn't have one; we need it for the ApplicantProfile factory)

Create `database/factories/CountryFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Domain\Identity\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function definition(): array
    {
        static $counter = 0;
        $counter++;

        return [
            'name'       => fake()->country() . ' ' . $counter,
            'iso2'       => strtoupper(fake()->lexify('??')) . $counter,
            'iso3'       => strtoupper(fake()->lexify('???')) . $counter,
            'phone_code' => '+' . fake()->numberBetween(1, 999),
            'is_active'  => true,
        ];
    }
}
```

Also add `HasFactory` to the Country model. Edit `app/Domain/Identity/Models/Country.php`:

```php
<?php

namespace App\Domain\Identity\Models;

use Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'iso2',
        'iso3',
        'phone_code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
```

Also add `HasFactory` to `ApplicantProfile` model. Edit `app/Domain/Identity/Models/ApplicantProfile.php` — add `use HasFactory;` trait and the import:

```php
use Database\Factories\ApplicantProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
```

Add the trait after `use HasUlids;`:
```php
use HasFactory, HasUlids;
```

- [ ] **Step 3: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: Commit**

```bash
git add database/factories/ app/Domain/Identity/Models/
git commit -m "feat(m1): add ApplicantProfile and Country factories, HasFactory traits"
```

---

## Task 6: Domain Actions — RegisterApplicant + CompleteApplicantProfile

**Files:**
- Create: `app/Domain/Identity/Data/ApplicantProfileData.php`
- Create: `app/Domain/Identity/Actions/RegisterApplicant.php`
- Create: `app/Domain/Identity/Actions/CompleteApplicantProfile.php`
- Create: `tests/Unit/RegisterApplicantActionTest.php`
- Create: `tests/Unit/CompleteApplicantProfileActionTest.php`

- [ ] **Step 1: Write the failing test for RegisterApplicant**

```bash
php artisan make:test --phpunit --unit RegisterApplicantActionTest --no-interaction
```

Replace the generated file with:

```php
<?php

namespace Tests\Unit;

use App\Domain\Identity\Actions\RegisterApplicant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RegisterApplicantActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'applicant', 'guard_name' => 'web']);
    }

    public function test_creates_user_with_hashed_password(): void
    {
        $user = RegisterApplicant::run('Jane Doe', 'jane@example.com', 'secret123');

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com', 'name' => 'Jane Doe']);
        $this->assertNotEquals('secret123', $user->password);
    }

    public function test_assigns_applicant_role(): void
    {
        $user = RegisterApplicant::run('Jane Doe', 'jane@example.com', 'secret123');

        $this->assertTrue($user->hasRole('applicant'));
    }

    public function test_does_not_assign_staff_roles(): void
    {
        $user = RegisterApplicant::run('Jane Doe', 'jane@example.com', 'secret123');

        $this->assertFalse($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('super_admin'));
    }

    public function test_two_factor_disabled_by_default(): void
    {
        $user = RegisterApplicant::run('Jane Doe', 'jane@example.com', 'secret123');

        $this->assertFalse($user->two_factor_enabled);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

```bash
php artisan test --compact --filter=RegisterApplicantActionTest
```

Expected: error — class `RegisterApplicant` not found.

- [ ] **Step 3: Create `app/Domain/Identity/Actions/RegisterApplicant.php`**

```php
<?php

namespace App\Domain\Identity\Actions;

use App\Models\User;

class RegisterApplicant
{
    public static function run(string $name, string $email, string $password): User
    {
        $user = User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => $password,
        ]);

        $user->assignRole('applicant');

        return $user;
    }
}
```

- [ ] **Step 4: Run test — verify it passes**

```bash
php artisan test --compact --filter=RegisterApplicantActionTest
```

Expected: 4 passing.

- [ ] **Step 5: Write the failing test for CompleteApplicantProfile**

```bash
php artisan make:test --phpunit --unit CompleteApplicantProfileActionTest --no-interaction
```

```php
<?php

namespace Tests\Unit;

use App\Domain\Identity\Actions\CompleteApplicantProfile;
use App\Domain\Identity\Data\ApplicantProfileData;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Models\User;
use Database\Factories\CountryFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteApplicantProfileActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_profile_for_user(): void
    {
        $user    = User::factory()->create();
        $country = \App\Domain\Identity\Models\Country::factory()->create();

        $data = new ApplicantProfileData(
            firstName:             'Jane',
            lastName:              'Doe',
            middleName:            null,
            dateOfBirth:           '1990-05-15',
            gender:                'female',
            nationalityId:         $country->id,
            countryOfResidenceId:  $country->id,
            passportNumber:        'AB1234567',
            passportExpiryDate:    '2030-01-01',
            phone:                 '+44 7911 123456',
            addressLine1:          '10 Downing Street',
            addressLine2:          null,
            city:                  'London',
            state:                 null,
            postalCode:            'SW1A 2AA',
        );

        $profile = CompleteApplicantProfile::run($user, $data);

        $this->assertInstanceOf(ApplicantProfile::class, $profile);
        $this->assertEquals($user->id, $profile->user_id);
        $this->assertEquals('Jane', $profile->first_name);
    }

    public function test_updates_existing_profile(): void
    {
        $user    = User::factory()->create();
        $country = \App\Domain\Identity\Models\Country::factory()->create();
        ApplicantProfile::factory()->create(['user_id' => $user->id, 'nationality_id' => $country->id, 'country_of_residence_id' => $country->id]);

        $data = new ApplicantProfileData(
            firstName:             'Updated',
            lastName:              'Name',
            middleName:            null,
            dateOfBirth:           '1990-05-15',
            gender:                'female',
            nationalityId:         $country->id,
            countryOfResidenceId:  $country->id,
            passportNumber:        'ZZ9876543',
            passportExpiryDate:    '2032-01-01',
            phone:                 '+1 555 000 0000',
            addressLine1:          '1 Main St',
            addressLine2:          null,
            city:                  'Springfield',
            state:                 'IL',
            postalCode:            '62701',
        );

        $profile = CompleteApplicantProfile::run($user, $data);

        $this->assertEquals('Updated', $profile->first_name);
        $this->assertEquals(1, ApplicantProfile::where('user_id', $user->id)->count());
    }

    public function test_encrypts_sensitive_fields(): void
    {
        $user    = User::factory()->create();
        $country = \App\Domain\Identity\Models\Country::factory()->create();

        $data = new ApplicantProfileData(
            firstName: 'Jane', lastName: 'Doe', middleName: null,
            dateOfBirth: '1990-05-15', gender: 'female',
            nationalityId: $country->id, countryOfResidenceId: $country->id,
            passportNumber: 'SECRET123', passportExpiryDate: '2030-01-01',
            phone: '+44 secret', addressLine1: '1 St', addressLine2: null,
            city: 'London', state: null, postalCode: null,
        );

        CompleteApplicantProfile::run($user, $data);

        $raw = \Illuminate\Support\Facades\DB::table('applicant_profiles')
            ->where('user_id', $user->id)
            ->value('passport_number');

        $this->assertNotEquals('SECRET123', $raw);
    }
}
```

- [ ] **Step 6: Run to verify it fails**

```bash
php artisan test --compact --filter=CompleteApplicantProfileActionTest
```

- [ ] **Step 7: Create `app/Domain/Identity/Data/ApplicantProfileData.php`**

```php
<?php

namespace App\Domain\Identity\Data;

readonly class ApplicantProfileData
{
    public function __construct(
        public string  $firstName,
        public string  $lastName,
        public ?string $middleName,
        public string  $dateOfBirth,
        public string  $gender,
        public int     $nationalityId,
        public int     $countryOfResidenceId,
        public string  $passportNumber,
        public string  $passportExpiryDate,
        public string  $phone,
        public string  $addressLine1,
        public ?string $addressLine2,
        public string  $city,
        public ?string $state,
        public ?string $postalCode,
    ) {}
}
```

- [ ] **Step 8: Create `app/Domain/Identity/Actions/CompleteApplicantProfile.php`**

```php
<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Data\ApplicantProfileData;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Models\User;

class CompleteApplicantProfile
{
    public static function run(User $user, ApplicantProfileData $data): ApplicantProfile
    {
        return ApplicantProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_name'              => $data->firstName,
                'last_name'               => $data->lastName,
                'middle_name'             => $data->middleName,
                'date_of_birth'           => $data->dateOfBirth,
                'gender'                  => $data->gender,
                'nationality_id'          => $data->nationalityId,
                'country_of_residence_id' => $data->countryOfResidenceId,
                'passport_number'         => $data->passportNumber,
                'passport_expiry_date'    => $data->passportExpiryDate,
                'phone'                   => $data->phone,
                'address_line_1'          => $data->addressLine1,
                'address_line_2'          => $data->addressLine2,
                'city'                    => $data->city,
                'state'                   => $data->state,
                'postal_code'             => $data->postalCode,
            ]
        );
    }
}
```

- [ ] **Step 9: Run tests — verify they pass**

```bash
php artisan test --compact --filter=RegisterApplicantActionTest,CompleteApplicantProfileActionTest
```

Expected: 7 passing.

- [ ] **Step 10: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 11: Commit**

```bash
git add app/Domain/Identity/ tests/Unit/RegisterApplicantActionTest.php tests/Unit/CompleteApplicantProfileActionTest.php
git commit -m "feat(m1): RegisterApplicant and CompleteApplicantProfile domain actions"
```

---

## Task 7: Rate Limiters + bootstrap/app.php Fix

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `bootstrap/app.php`

- [ ] **Step 1: Add login + mfa-otp rate limiters to AppServiceProvider**

In the `boot()` method, after the existing rate limiters, add:

```php
RateLimiter::for('login', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip());
});

RateLimiter::for('mfa-otp', function (Request $request) {
    return Limit::perMinutes(15, 3)->by($request->input('email', $request->ip()));
});

RateLimiter::for('register', function (Request $request) {
    return Limit::perMinute(10)->by($request->ip());
});
```

- [ ] **Step 2: Fix guest redirect in `bootstrap/app.php`**

Change:

```php
$middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));
```

To:

```php
$middleware->redirectGuestsTo(fn () => route('login'));
```

- [ ] **Step 3: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: Commit**

```bash
git add app/Providers/AppServiceProvider.php bootstrap/app.php
git commit -m "feat(m1): add login/mfa-otp/register rate limiters, fix guest redirect to applicant login"
```

---

## Task 8: Auth Form Requests + Controllers

**Files:**
- Create: `app/Http/Requests/Auth/RegisterRequest.php`
- Create: `app/Http/Requests/Auth/LoginRequest.php`
- Create: `app/Http/Controllers/Auth/RegisteredUserController.php`
- Create: `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- Create: `app/Http/Controllers/Auth/EmailVerificationController.php`
- Create: `app/Http/Controllers/Auth/PasswordResetLinkController.php`
- Create: `app/Http/Controllers/Auth/NewPasswordController.php`
- Create: `app/Http/Controllers/Auth/MfaChallengeController.php`

- [ ] **Step 1: Create `app/Http/Requests/Auth/RegisterRequest.php`**

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ];
    }
}
```

- [ ] **Step 2: Create `app/Http/Requests/Auth/LoginRequest.php`**

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
```

- [ ] **Step 3: Create `app/Http/Controllers/Auth/RegisteredUserController.php`**

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\RegisterApplicant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('pages.auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = RegisterApplicant::run(
            $request->validated('name'),
            $request->validated('email'),
            $request->validated('password'),
        );

        Auth::login($user);

        $user->sendEmailVerificationNotification();

        return redirect()->route('verification.notice');
    }
}
```

- [ ] **Step 4: Create `app/Http/Controllers/Auth/AuthenticatedSessionController.php`**

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('pages.auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'These credentials do not match our records.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = Auth::user();

        if ($user->two_factor_enabled) {
            $otp  = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            cache()->put("mfa.otp.{$user->id}", $otp, now()->addMinutes(15));

            $user->notify(new \App\Notifications\MfaOtpNotification($otp));

            Auth::logout();
            $request->session()->put('mfa_user_id', $user->id);

            return redirect()->route('mfa.challenge');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
```

- [ ] **Step 5: Create the MFA notification `app/Notifications/MfaOtpNotification.php`**

```php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MfaOtpNotification extends Notification
{
    public function __construct(private readonly string $otp) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your sign-in code')
            ->line("Your one-time sign-in code is: **{$this->otp}**")
            ->line('This code expires in 15 minutes.')
            ->line('If you did not request this code, you can safely ignore this email.');
    }
}
```

- [ ] **Step 6: Create `app/Http/Controllers/Auth/MfaChallengeController.php`**

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MfaChallengeController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('mfa_user_id')) {
            return redirect()->route('login');
        }

        return view('pages.auth.mfa-challenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'size:6']]);

        $userId = $request->session()->get('mfa_user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $storedOtp = cache()->get("mfa.otp.{$userId}");

        if (! $storedOtp || $storedOtp !== $request->input('code')) {
            return back()->withErrors(['code' => 'The code is invalid or has expired.']);
        }

        cache()->forget("mfa.otp.{$userId}");
        $request->session()->forget('mfa_user_id');

        $user = User::findOrFail($userId);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
```

- [ ] **Step 7: Create `app/Http/Controllers/Auth/EmailVerificationController.php`**

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function notice(): View|RedirectResponse
    {
        if (request()->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        return view('pages.auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->fulfill();
            event(new Verified($request->user()));
        }

        return redirect()->route('dashboard');
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('success', 'A new verification link has been sent to your email.');
    }
}
```

- [ ] **Step 8: Create `app/Http/Controllers/Auth/PasswordResetLinkController.php`**

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('pages.auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        return back()->with('success', 'If that email address is registered, you will receive a password reset link.');
    }
}
```

- [ ] **Step 9: Create `app/Http/Controllers/Auth/NewPasswordController.php`**

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request): View
    {
        return view('pages.auth.reset-password', ['token' => $request->route('token'), 'email' => $request->query('email')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::min(10)->letters()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => Hash::make($password)])
                    ->setRememberToken(Str::random(60));
                $user->save();
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Your password has been reset.')
            : back()->withErrors(['email' => [__($status)]]);
    }
}
```

- [ ] **Step 10: Create `app/Http/Controllers/DashboardController.php`**

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $profile = $request->user()->applicantProfile;

        return view('pages.dashboard', compact('profile'));
    }
}
```

- [ ] **Step 11: Create `app/Http/Middleware/EnsureProfileComplete.php`**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->hasRole('applicant') && ! $user->applicantProfile()->exists()) {
            return redirect()->route('profile.setup');
        }

        return $next($request);
    }
}
```

- [ ] **Step 12: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 13: Commit**

```bash
git add app/Http/ app/Notifications/
git commit -m "feat(m1): auth controllers, form requests, MFA OTP, EnsureProfileComplete middleware"
```

---

## Task 9: Auth Routes

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1: Replace `routes/web.php` with:**

```php
<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\MfaChallengeController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\ExportDownloadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StripeWebhookController;
use App\Livewire\Profile\SetupWizard;
use Illuminate\Support\Facades\Route;

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:register');

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');

    Route::get('/mfa-challenge', [MfaChallengeController::class, 'create'])->name('mfa.challenge');
    Route::post('/mfa-challenge', [MfaChallengeController::class, 'store'])->middleware('throttle:mfa-otp');
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Email verification
    Route::get('/verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // Profile setup (verified + before profile complete — no EnsureProfileComplete here)
    Route::get('/profile/setup', SetupWizard::class)
        ->middleware('verified')
        ->name('profile.setup');

    // Protected applicant area
    Route::middleware(['verified', \App\Http\Middleware\EnsureProfileComplete::class])->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
    });
});

// Redirect / to dashboard or login
Route::get('/', fn () => redirect()->route('dashboard'));

// Document & export downloads
Route::get('/documents/{version}/download', [DocumentDownloadController::class, 'download'])
    ->name('documents.download')
    ->middleware(['auth', 'signed', 'throttle:document-download']);

Route::get('/exports/{ulid}/download', [ExportDownloadController::class, 'download'])
    ->name('exports.download')
    ->middleware(['auth', 'throttle:document-download']);

// Stripe webhook (no auth)
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->name('webhooks.stripe')
    ->middleware('throttle:webhook');
```

- [ ] **Step 2: Verify routes register without error**

```bash
php artisan route:list --path=/ --except-vendor 2>&1 | head -40
```

Expected: register, login, logout, verify-email, mfa-challenge, password.request, profile.setup, dashboard routes visible.

- [ ] **Step 3: Commit**

```bash
git add routes/web.php
git commit -m "feat(m1): applicant portal auth routes and dashboard route"
```

---

## Task 10: Auth Blade Views

**Files:** `resources/views/pages/auth/`

- [ ] **Step 1: Create `resources/views/pages/auth/register.blade.php`**

```blade
<x-guest-layout title="Create your account">
    <div class="w-full max-w-md space-y-6">
        <div class="text-center">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create your account</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Already have an account?
                <a href="{{ route('login') }}" class="font-medium text-blue-600 hover:text-blue-500">Sign in</a>
            </p>
        </div>

        <x-card>
            <form method="POST" action="{{ route('register') }}" class="space-y-4">
                @csrf

                <x-input name="name" label="Full name" :required="true" value="{{ old('name') }}" autocomplete="name" />
                <x-input name="email" label="Email address" type="email" :required="true" value="{{ old('email') }}" autocomplete="email" />
                <x-input name="password" label="Password" type="password" :required="true"
                    hint="At least 10 characters with letters and numbers" autocomplete="new-password" />
                <x-input name="password_confirmation" label="Confirm password" type="password" :required="true" autocomplete="new-password" />

                <x-button type="submit" class="w-full justify-center">Create account</x-button>
            </form>
        </x-card>
    </div>
</x-guest-layout>
```

- [ ] **Step 2: Create `resources/views/pages/auth/login.blade.php`**

```blade
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
```

- [ ] **Step 3: Create `resources/views/pages/auth/verify-email.blade.php`**

```blade
<x-guest-layout title="Verify your email">
    <div class="w-full max-w-md space-y-6">
        <div class="text-center">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Verify your email address</h1>
        </div>
        <x-card>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                Thanks for registering! Before you continue, please verify your email address by clicking the link we just sent you.
            </p>

            @if(session('success'))
                <x-alert type="success" class="mb-4">{{ session('success') }}</x-alert>
            @endif

            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <x-button type="submit" variant="secondary" class="w-full justify-center">Resend verification email</x-button>
            </form>

            <div class="mt-4 text-center">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Sign out</button>
                </form>
            </div>
        </x-card>
    </div>
</x-guest-layout>
```

- [ ] **Step 4: Create `resources/views/pages/auth/mfa-challenge.blade.php`**

```blade
<x-guest-layout title="Two-factor verification">
    <div class="w-full max-w-md space-y-6">
        <div class="text-center">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Two-factor verification</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">We sent a 6-digit code to your email address.</p>
        </div>
        <x-card>
            <form method="POST" action="{{ route('mfa.challenge') }}" class="space-y-4">
                @csrf
                <x-input name="code" label="Verification code" :required="true"
                    hint="Enter the 6-digit code from your email"
                    autocomplete="one-time-code"
                    inputmode="numeric"
                    maxlength="6"
                    autofocus />
                <x-button type="submit" class="w-full justify-center">Verify</x-button>
            </form>
            <div class="mt-4 text-center">
                <a href="{{ route('login') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Back to sign in</a>
            </div>
        </x-card>
    </div>
</x-guest-layout>
```

- [ ] **Step 5: Create `resources/views/pages/auth/forgot-password.blade.php`**

```blade
<x-guest-layout title="Reset your password">
    <div class="w-full max-w-md space-y-6">
        <div class="text-center">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Forgot your password?</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Enter your email and we'll send you a reset link.</p>
        </div>
        <x-card>
            @if(session('success'))
                <x-alert type="success" class="mb-4">{{ session('success') }}</x-alert>
            @endif
            <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                @csrf
                <x-input name="email" label="Email address" type="email" :required="true" value="{{ old('email') }}" autofocus />
                <x-button type="submit" class="w-full justify-center">Send reset link</x-button>
            </form>
            <div class="mt-4 text-center">
                <a href="{{ route('login') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Back to sign in</a>
            </div>
        </x-card>
    </div>
</x-guest-layout>
```

- [ ] **Step 6: Create `resources/views/pages/auth/reset-password.blade.php`**

```blade
<x-guest-layout title="Set new password">
    <div class="w-full max-w-md space-y-6">
        <div class="text-center">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Set new password</h1>
        </div>
        <x-card>
            <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <x-input name="email" label="Email address" type="email" :required="true" value="{{ $email ?? old('email') }}" autocomplete="email" />
                <x-input name="password" label="New password" type="password" :required="true"
                    hint="At least 10 characters with letters and numbers" autocomplete="new-password" />
                <x-input name="password_confirmation" label="Confirm new password" type="password" :required="true" autocomplete="new-password" />
                <x-button type="submit" class="w-full justify-center">Reset password</x-button>
            </form>
        </x-card>
    </div>
</x-guest-layout>
```

- [ ] **Step 7: Build to check for Blade errors**

```bash
npm run build
```

- [ ] **Step 8: Commit**

```bash
git add resources/views/pages/auth/
git commit -m "feat(m1): auth Blade views (register, login, verify-email, mfa-challenge, forgot-password, reset-password)"
```

---

## Task 11: Profile Wizard Livewire Component

**Files:**
- Create: `app/Livewire/Profile/SetupWizard.php`
- Create: `resources/views/livewire/profile/setup-wizard.blade.php`

- [ ] **Step 1: Create `app/Livewire/Profile/SetupWizard.php`**

```php
<?php

namespace App\Livewire\Profile;

use App\Domain\Identity\Actions\CompleteApplicantProfile;
use App\Domain\Identity\Data\ApplicantProfileData;
use App\Domain\Identity\Models\Country;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

class SetupWizard extends Component
{
    public int $currentStep = 1;

    public bool $saved = false;

    // Step 1 — personal info
    public string $firstName = '';
    public string $lastName = '';
    public string $middleName = '';
    public string $dateOfBirth = '';
    public string $gender = '';
    public string $nationalityId = '';
    public string $countryOfResidenceId = '';

    // Step 2 — passport + contact
    public string $passportNumber = '';
    public string $passportExpiryDate = '';
    public string $phone = '';
    public string $addressLine1 = '';
    public string $addressLine2 = '';
    public string $city = '';
    public string $state = '';
    public string $postalCode = '';

    /** @var array<string, array<int, mixed>> */
    protected array $stepRules = [
        1 => [
            'firstName'            => ['required', 'string', 'max:100'],
            'lastName'             => ['required', 'string', 'max:100'],
            'middleName'           => ['nullable', 'string', 'max:100'],
            'dateOfBirth'          => ['required', 'date', 'before:-18 years'],
            'gender'               => ['required', 'in:male,female,other'],
            'nationalityId'        => ['required', 'exists:countries,id'],
            'countryOfResidenceId' => ['required', 'exists:countries,id'],
        ],
        2 => [
            'passportNumber'     => ['required', 'string', 'max:20'],
            'passportExpiryDate' => ['required', 'date', 'after:today'],
            'phone'              => ['required', 'string', 'max:30'],
            'addressLine1'       => ['required', 'string', 'max:255'],
            'addressLine2'       => ['nullable', 'string', 'max:255'],
            'city'               => ['required', 'string', 'max:100'],
            'state'              => ['nullable', 'string', 'max:100'],
            'postalCode'         => ['nullable', 'string', 'max:20'],
        ],
    ];

    public function mount(): void
    {
        $profile = auth()->user()->applicantProfile;

        if ($profile) {
            $this->fill([
                'firstName'            => $profile->first_name,
                'lastName'             => $profile->last_name,
                'middleName'           => $profile->middle_name ?? '',
                'dateOfBirth'          => $profile->date_of_birth?->format('Y-m-d') ?? '',
                'gender'               => $profile->gender,
                'nationalityId'        => (string) $profile->nationality_id,
                'countryOfResidenceId' => (string) $profile->country_of_residence_id,
                'passportNumber'       => $profile->passport_number,
                'passportExpiryDate'   => $profile->passport_expiry_date?->format('Y-m-d') ?? '',
                'phone'                => $profile->phone,
                'addressLine1'         => $profile->address_line_1,
                'addressLine2'         => $profile->address_line_2 ?? '',
                'city'                 => $profile->city,
                'state'                => $profile->state ?? '',
                'postalCode'           => $profile->postal_code ?? '',
            ]);
        }
    }

    public function nextStep(): void
    {
        $this->validate($this->stepRules[$this->currentStep]);
        $this->saveCurrentStep();
        $this->currentStep = 2;
    }

    public function complete(): void
    {
        $this->validate($this->stepRules[2]);
        $this->saveCurrentStep();
        $this->redirect(route('dashboard'));
    }

    private function saveCurrentStep(): void
    {
        $data = new ApplicantProfileData(
            firstName:            $this->firstName,
            lastName:             $this->lastName,
            middleName:           $this->middleName ?: null,
            dateOfBirth:          $this->dateOfBirth,
            gender:               $this->gender,
            nationalityId:        (int) $this->nationalityId,
            countryOfResidenceId: (int) $this->countryOfResidenceId,
            passportNumber:       $this->passportNumber ?: 'PENDING',
            passportExpiryDate:   $this->passportExpiryDate ?: '2099-01-01',
            phone:                $this->phone ?: 'PENDING',
            addressLine1:         $this->addressLine1 ?: 'PENDING',
            addressLine2:         $this->addressLine2 ?: null,
            city:                 $this->city ?: 'PENDING',
            state:                $this->state ?: null,
            postalCode:           $this->postalCode ?: null,
        );

        CompleteApplicantProfile::run(auth()->user(), $data);

        $this->saved = true;
        $this->dispatch('saved');
    }

    public function countries(): Collection
    {
        return Country::where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function render(): View
    {
        return view('livewire.profile.setup-wizard', [
            'countries' => $this->countries(),
        ])->layout('layouts.app', ['title' => 'Profile Setup']);
    }
}
```

- [ ] **Step 2: Create `resources/views/livewire/profile/setup-wizard.blade.php`**

```blade
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
```

- [ ] **Step 3: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: Build**

```bash
npm run build
```

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/ resources/views/livewire/
git commit -m "feat(m1): profile wizard Livewire component (2-step personal + passport)"
```

---

## Task 12: Dashboard View

**Files:**
- Create: `resources/views/pages/dashboard.blade.php`

- [ ] **Step 1: Create the dashboard view**

```blade
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
```

- [ ] **Step 2: Build**

```bash
npm run build
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/pages/dashboard.blade.php
git commit -m "feat(m1): dashboard view with empty state"
```

---

## Task 13: Registration Feature Tests

**Files:**
- Create: `tests/Feature/RegistrationTest.php`

- [ ] **Step 1: Create the test**

```bash
php artisan make:test --phpunit RegistrationTest --no-interaction
```

Replace with:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'applicant', 'guard_name' => 'web']);
    }

    public function test_registration_page_is_accessible_to_guests(): void
    {
        $this->get(route('register'))->assertOk();
    }

    public function test_new_user_can_register(): void
    {
        Event::fake();

        $response = $this->post(route('register'), [
            'name'                  => 'Jane Doe',
            'email'                 => 'jane@example.com',
            'password'              => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
        $this->assertAuthenticated();

        $user = User::where('email', 'jane@example.com')->first();
        $this->assertTrue($user->hasRole('applicant'));
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->post(route('register'), [
            'name'                  => 'Jane Doe',
            'email'                 => 'taken@example.com',
            'password'              => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_weak_password_is_rejected(): void
    {
        $response = $this->post(route('register'), [
            'name'                  => 'Jane Doe',
            'email'                 => 'jane@example.com',
            'password'              => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_password_confirmation_mismatch_is_rejected(): void
    {
        $response = $this->post(route('register'), [
            'name'                  => 'Jane Doe',
            'email'                 => 'jane@example.com',
            'password'              => 'SecurePass123',
            'password_confirmation' => 'DifferentPass123',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_authenticated_user_cannot_see_register_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('register'))->assertRedirect();
    }
}
```

- [ ] **Step 2: Run the tests**

```bash
php artisan test --compact --filter=RegistrationTest
```

Expected: all passing.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/RegistrationTest.php
git commit -m "test(m1): registration flow tests"
```

---

## Task 14: Login + MFA Feature Tests

**Files:**
- Create: `tests/Feature/LoginTest.php`
- Create: `tests/Feature/MfaChallengeTest.php`

- [ ] **Step 1: Create `tests/Feature/LoginTest.php`**

```bash
php artisan make:test --phpunit LoginTest --no-interaction
```

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_accessible(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('CorrectPass1')]);

        $response = $this->post(route('login'), [
            'email'    => $user->email,
            'password' => 'CorrectPass1',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('CorrectPass1')]);

        $this->post(route('login'), [
            'email'    => $user->email,
            'password' => 'WrongPass',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $this->post(route('login'), [
            'email'    => 'nobody@example.com',
            'password' => 'AnyPass123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_mfa_enabled_user_is_redirected_to_challenge(): void
    {
        $user = User::factory()->create([
            'password'           => bcrypt('CorrectPass1'),
            'two_factor_enabled' => true,
        ]);

        $response = $this->post(route('login'), [
            'email'    => $user->email,
            'password' => 'CorrectPass1',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('mfa.challenge'));
    }
}
```

- [ ] **Step 2: Create `tests/Feature/MfaChallengeTest.php`**

```bash
php artisan make:test --phpunit MfaChallengeTest --no-interaction
```

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MfaChallengeTest extends TestCase
{
    use RefreshDatabase;

    public function test_mfa_page_requires_mfa_session_key(): void
    {
        $this->get(route('mfa.challenge'))->assertRedirect(route('login'));
    }

    public function test_valid_otp_logs_user_in(): void
    {
        $user = User::factory()->create(['two_factor_enabled' => true]);
        Cache::put("mfa.otp.{$user->id}", '123456', now()->addMinutes(15));

        $response = $this->withSession(['mfa_user_id' => $user->id])
            ->post(route('mfa.challenge'), ['code' => '123456']);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_invalid_otp_is_rejected(): void
    {
        $user = User::factory()->create(['two_factor_enabled' => true]);
        Cache::put("mfa.otp.{$user->id}", '123456', now()->addMinutes(15));

        $this->withSession(['mfa_user_id' => $user->id])
            ->post(route('mfa.challenge'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_expired_otp_is_rejected(): void
    {
        $user = User::factory()->create(['two_factor_enabled' => true]);
        // No OTP in cache = expired

        $this->withSession(['mfa_user_id' => $user->id])
            ->post(route('mfa.challenge'), ['code' => '123456'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_otp_is_consumed_after_use(): void
    {
        $user = User::factory()->create(['two_factor_enabled' => true]);
        Cache::put("mfa.otp.{$user->id}", '123456', now()->addMinutes(15));

        $this->withSession(['mfa_user_id' => $user->id])
            ->post(route('mfa.challenge'), ['code' => '123456']);

        $this->assertNull(Cache::get("mfa.otp.{$user->id}"));
    }
}
```

- [ ] **Step 3: Run both test files**

```bash
php artisan test --compact --filter=LoginTest,MfaChallengeTest
```

Expected: all passing.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/LoginTest.php tests/Feature/MfaChallengeTest.php
git commit -m "test(m1): login and MFA challenge tests"
```

---

## Task 15: Email Verification + Password Reset Tests

**Files:**
- Create: `tests/Feature/EmailVerificationTest.php`
- Create: `tests/Feature/PasswordResetTest.php`

- [ ] **Step 1: Create `tests/Feature/EmailVerificationTest.php`**

```bash
php artisan make:test --phpunit EmailVerificationTest --no-interaction
```

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_email_notice_shown_to_unverified_user(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get(route('verification.notice'))->assertOk();
    }

    public function test_verified_user_redirected_from_notice(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('verification.notice'))->assertRedirect(route('dashboard'));
    }

    public function test_email_can_be_verified_via_signed_url(): void
    {
        Event::fake();
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->actingAs($user)->get($url)->assertRedirect(route('dashboard'));
        $this->assertNotNull($user->fresh()->email_verified_at);
        Event::assertDispatched(Verified::class);
    }

    public function test_verification_fails_with_wrong_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong@email.com')]
        );

        $this->actingAs($user)->get($url)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }
}
```

- [ ] **Step 2: Create `tests/Feature/PasswordResetTest.php`**

```bash
php artisan make:test --phpunit PasswordResetTest --no-interaction
```

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_is_accessible(): void
    {
        $this->get(route('password.request'))->assertOk();
    }

    public function test_reset_link_request_does_not_reveal_if_email_exists(): void
    {
        // Always responds with success regardless of whether email exists
        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHas('success');
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user  = User::factory()->create();
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'NewSecurePass1',
            'password_confirmation' => 'NewSecurePass1',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NewSecurePass1', $user->fresh()->password));
    }

    public function test_reset_fails_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $this->post(route('password.update'), [
            'token'                 => 'invalid-token',
            'email'                 => $user->email,
            'password'              => 'NewSecurePass1',
            'password_confirmation' => 'NewSecurePass1',
        ])->assertSessionHasErrors('email');
    }
}
```

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact --filter=EmailVerificationTest,PasswordResetTest
```

Expected: all passing.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/EmailVerificationTest.php tests/Feature/PasswordResetTest.php
git commit -m "test(m1): email verification and password reset tests"
```

---

## Task 16: Profile Wizard + Dashboard Tests

**Files:**
- Create: `tests/Feature/ProfileWizardTest.php`
- Create: `tests/Feature/DashboardTest.php`

- [ ] **Step 1: Create `tests/Feature/ProfileWizardTest.php`**

```bash
php artisan make:test --phpunit ProfileWizardTest --no-interaction
```

```php
<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Livewire\Profile\SetupWizard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProfileWizardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Country $country;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'applicant', 'guard_name' => 'web']);

        $this->user    = User::factory()->create(['email_verified_at' => now()]);
        $this->country = Country::factory()->create();
    }

    public function test_profile_setup_page_requires_auth(): void
    {
        $this->get(route('profile.setup'))->assertRedirect(route('login'));
    }

    public function test_profile_setup_page_requires_verified_email(): void
    {
        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->get(route('profile.setup'))->assertRedirect(route('verification.notice'));
    }

    public function test_step_1_validates_required_fields(): void
    {
        Livewire::actingAs($this->user)
            ->test(SetupWizard::class)
            ->call('nextStep')
            ->assertHasErrors(['firstName', 'lastName', 'dateOfBirth', 'gender', 'nationalityId', 'countryOfResidenceId']);
    }

    public function test_step_1_advances_to_step_2_with_valid_data(): void
    {
        Livewire::actingAs($this->user)
            ->test(SetupWizard::class)
            ->set('firstName', 'Jane')
            ->set('lastName', 'Doe')
            ->set('dateOfBirth', '1990-01-15')
            ->set('gender', 'female')
            ->set('nationalityId', (string) $this->country->id)
            ->set('countryOfResidenceId', (string) $this->country->id)
            ->call('nextStep')
            ->assertSet('currentStep', 2)
            ->assertHasNoErrors();
    }

    public function test_completing_wizard_creates_profile(): void
    {
        Livewire::actingAs($this->user)
            ->test(SetupWizard::class)
            ->set('firstName', 'Jane')
            ->set('lastName', 'Doe')
            ->set('dateOfBirth', '1990-01-15')
            ->set('gender', 'female')
            ->set('nationalityId', (string) $this->country->id)
            ->set('countryOfResidenceId', (string) $this->country->id)
            ->call('nextStep')
            ->set('passportNumber', 'AB1234567')
            ->set('passportExpiryDate', '2030-01-01')
            ->set('phone', '+44 7911 000000')
            ->set('addressLine1', '10 Downing Street')
            ->set('city', 'London')
            ->call('complete');

        $this->assertDatabaseHas('applicant_profiles', [
            'user_id'    => $this->user->id,
            'first_name' => 'Jane',
            'city'       => 'London',
        ]);
    }

    public function test_wizard_pre_fills_existing_profile(): void
    {
        $profile = ApplicantProfile::factory()->create([
            'user_id'                  => $this->user->id,
            'first_name'               => 'Existing',
            'nationality_id'           => $this->country->id,
            'country_of_residence_id'  => $this->country->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(SetupWizard::class)
            ->assertSet('firstName', 'Existing');
    }
}
```

- [ ] **Step 2: Create `tests/Feature/DashboardTest.php`**

```bash
php artisan make:test --phpunit DashboardTest --no-interaction
```

```php
<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'applicant', 'guard_name' => 'web']);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_unverified_user_is_redirected_to_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    }

    public function test_applicant_without_profile_redirected_to_setup(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('applicant');

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('profile.setup'));
    }

    public function test_applicant_with_profile_can_view_dashboard(): void
    {
        $user    = User::factory()->create(['email_verified_at' => now()]);
        $country = Country::factory()->create();
        $user->assignRole('applicant');

        ApplicantProfile::factory()->create([
            'user_id'                 => $user->id,
            'nationality_id'          => $country->id,
            'country_of_residence_id' => $country->id,
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_non_applicant_role_user_can_view_dashboard(): void
    {
        // Staff users with no applicant profile should not be blocked by EnsureProfileComplete
        $user = User::factory()->create(['email_verified_at' => now()]);
        // No role — EnsureProfileComplete only redirects for 'applicant' role users

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }
}
```

- [ ] **Step 3: Run both test files**

```bash
php artisan test --compact --filter=ProfileWizardTest,DashboardTest
```

Expected: all passing.

- [ ] **Step 4: Run Pint across all new files**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/ProfileWizardTest.php tests/Feature/DashboardTest.php
git commit -m "test(m1): profile wizard and dashboard tests"
```

---

## Task 17: Final verification

- [ ] **Step 1: Run the full test suite**

```bash
php artisan test --compact
```

Expected: all tests pass including pre-existing M0–M7 tests.

- [ ] **Step 2: Build assets**

```bash
npm run build
```

Expected: zero errors.

- [ ] **Step 3: Spot check routes**

```bash
php artisan route:list --except-vendor 2>&1 | grep -E "login|register|dashboard|profile|mfa|verify|password"
```

Expected: all 14+ applicant portal routes listed.

- [ ] **Step 4: Final commit if any Pint fixes remain**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "chore(m1): pint formatting pass"
```

---

## Self-Review

**Spec coverage check:**

| M1 Deliverable | Covered by task |
|---|---|
| Register page | Tasks 8, 10, 13 |
| Login page | Tasks 8, 10, 14 |
| Email verification | Tasks 8, 10, 15 |
| MFA challenge | Tasks 8, 10, 14 |
| Forgot / reset password | Tasks 8, 10, 15 |
| Profile wizard (personal + passport) | Tasks 6, 7, 11, 16 |
| Dashboard shell (empty state) | Tasks 8, 12, 16 |
| x-* Blade component library | Task 3 |
| Layouts (guest + app) | Task 2 |
| Tailwind 4 + Tabler Icons | Task 1 |
| Rate limiting (login, OTP, register) | Task 7 |
| Guest redirect fix | Task 7 |
| MustVerifyEmail | Task 4 |
| ApplicantProfile relationship on User | Task 4 |
| ApplicantProfile factory | Task 5 |
| EnsureProfileComplete middleware | Task 8 |

**Security rules verified:**
- No raw IDs in URLs — profile uses `auth()->user()`, dashboard uses `auth()`, no ID in route
- Passwords hashed — User model `'password' => 'hashed'` cast
- Passport/phone encrypted — ApplicantProfile model `'passport_number' => 'encrypted'` cast
- MFA challenge does not leak which account — same error for bad OTP and no session
- CSRF on all non-GET routes — Laravel default + not excluded in bootstrap/app.php
- Rate limiting — login (5/min), OTP (3/15min), register (10/min)
- Email verification required before dashboard access
