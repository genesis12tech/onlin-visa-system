# Applicant Dashboard — Implementation Spec

> Paste this file into Claude Code to implement the applicant dashboard.
> The mockup reference section describes exactly what the UI should look like.

Implement the **Applicant Dashboard** for the Visa Application System.

## Context

- Laravel 12 · PHP 8.3+ · Blade + Livewire 3 · Tailwind CSS 4
- CLAUDE.md is in the project root — read it before writing any code
- Applicant portal is Blade + Livewire ONLY — no React, no Inertia, no Vue
- Domain folder: app/Domain/ — Livewire components call Domain Actions or
  Eloquent queries directly; they never contain business logic themselves
- ULIDs for all primary keys. Never expose numeric IDs in routes or HTML
- Milestones 0–4 are complete: users, applicant_profiles, visa_applications,
  visa_types, countries, application_documents, document_types, invoices,
  notifications tables all exist with models and migrations
- ApplicationStatus enum exists at app/Domain/Applications/Enums/ApplicationStatus.php
- Shared Blade components (x-button, x-input, x-alert, x-card, x-badge,
  x-empty-state) already exist in resources/views/components/

---

## What to build

### Route

```php
// routes/web.php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', App\Livewire\ApplicantDashboard::class)
        ->name('dashboard');
});
```

### File list — create every file below

```
app/
  Livewire/
    ApplicantDashboard.php          ← root Livewire component
    NotificationBell.php            ← nested Livewire component

  View/
    Components/
      StatCard.php                  ← Blade component class
      ApplicationCard.php           ← Blade component class
      AlertBar.php                  ← Blade component class
      StatusBadge.php               ← Blade component class
      ProgressBar.php               ← Blade component class
      ActionTag.php                 ← Blade component class
      QuickActions.php              ← Blade component class

resources/
  views/
    livewire/
      applicant-dashboard.blade.php
      notification-bell.blade.php

    components/
      stat-card.blade.php
      application-card.blade.php
      alert-bar.blade.php
      status-badge.blade.php
      progress-bar.blade.php
      action-tag.blade.php
      quick-actions.blade.php
```

---

## Step 1 — ApplicantDashboard Livewire component

File: `app/Livewire/ApplicantDashboard.php`

```php
<?php

namespace App\Livewire;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Support\Collection;
use Livewire\Component;

class ApplicantDashboard extends Component
{
    public Collection $applications;
    public int $totalCount = 0;
    public int $inProgressCount = 0;
    public int $actionNeededCount = 0;
    public int $approvedCount = 0;
    public ?VisaApplication $actionRequiredApp = null;
    public array $quickActions = [];

    public function mount(): void
    {
        $this->loadDashboardData();
    }

    private function loadDashboardData(): void
    {
        $apps = VisaApplication::where('user_id', auth()->id())
            ->with([
                'visaType',
                'destinationCountry',
                'invoice',
                'applicationDocuments' => fn($q) => $q->with('documentType'),
            ])
            ->orderByDesc('updated_at')
            ->get();

        $this->applications     = $apps;
        $this->totalCount       = $apps->count();
        $this->inProgressCount  = $apps->whereIn('status',
            ApplicationStatus::inProgressValues())->count();
        $this->actionNeededCount = $apps->where('status',
            ApplicationStatus::InfoRequested->value)->count();
        $this->approvedCount    = $apps->where('status',
            ApplicationStatus::Approved->value)->count();

        $this->actionRequiredApp = $apps->first(
            fn($a) => $a->status === ApplicationStatus::InfoRequested->value
        );

        $this->quickActions = $this->buildQuickActions($apps);
    }

    private function buildQuickActions(Collection $apps): array
    {
        $actions = [];

        // Resubmit action — only when info_requested exists
        $infoApp = $apps->first(
            fn($a) => $a->status === ApplicationStatus::InfoRequested->value
        );
        if ($infoApp) {
            $actions[] = [
                'icon'  => 'upload',
                'label' => 'Resubmit documents',
                'href'  => route('applications.respond', $infoApp->id),
            ];
        }

        // Download decision letter — only when approved exists
        $approvedApp = $apps->first(
            fn($a) => $a->status === ApplicationStatus::Approved->value
        );
        if ($approvedApp) {
            $actions[] = [
                'icon'  => 'download',
                'label' => 'Download decision letter',
                'href'  => route('applications.decision-letter', $approvedApp->id),
            ];
        }

        // Always available
        $actions[] = [
            'icon'  => 'search',
            'label' => 'Track an application',
            'href'  => route('track'),
        ];

        return $actions;
    }

    public function render()
    {
        return view('livewire.applicant-dashboard')
            ->layout('layouts.app');
    }
}
```

---

## Step 2 — NotificationBell Livewire component

File: `app/Livewire/NotificationBell.php`

```php
<?php

namespace App\Livewire;

use Livewire\Component;

class NotificationBell extends Component
{
    public bool $open = false;
    public int $unreadCount = 0;

    public function mount(): void
    {
        $this->unreadCount = auth()->user()->unreadNotifications->count();
    }

    public function togglePanel(): void
    {
        $this->open = !$this->open;
        if ($this->open) {
            auth()->user()->unreadNotifications->markAsRead();
            $this->unreadCount = 0;
        }
    }

    public function render()
    {
        $notifications = auth()->user()
            ->notifications()
            ->latest()
            ->take(6)
            ->get();

        return view('livewire.notification-bell', compact('notifications'));
    }
}
```

---

## Step 3 — ApplicationStatus enum additions

Add these methods to `app/Domain/Applications/Enums/ApplicationStatus.php`
if they do not already exist:

```php
// Returns the values Claude Code can use in whereIn() calls
public static function inProgressValues(): array
{
    return [
        self::Submitted->value,
        self::PaymentPending->value,
        self::Paid->value,
        self::UnderReview->value,
        self::InfoRequested->value,
        self::Resubmitted->value,
        self::DocumentVerification->value,
        self::InterviewScheduled->value,
        self::DecisionPending->value,
    ];
}

// Maps enum value to Tailwind colour for x-status-badge
public function colour(): string
{
    return match($this) {
        self::Draft                 => 'gray',
        self::Submitted,
        self::PaymentPending        => 'blue',
        self::Paid,
        self::UnderReview,
        self::DocumentVerification,
        self::Resubmitted           => 'purple',
        self::InfoRequested         => 'amber',
        self::InterviewScheduled    => 'blue',
        self::DecisionPending       => 'purple',
        self::Approved              => 'green',
        self::Rejected              => 'red',
        self::Withdrawn,
        self::Closed                => 'gray',
    };
}

// Human-readable public label (same as the public tracking labels)
public function publicLabel(): string
{
    return match($this) {
        self::Draft                 => 'Draft',
        self::Submitted,
        self::PaymentPending        => 'Submitted',
        self::Paid,
        self::UnderReview,
        self::DocumentVerification,
        self::Resubmitted           => 'In review',
        self::InfoRequested         => 'Action required',
        self::InterviewScheduled    => 'Appointment scheduled',
        self::DecisionPending       => 'Decision pending',
        self::Approved              => 'Approved',
        self::Rejected              => 'Not approved',
        self::Withdrawn,
        self::Closed                => 'Closed',
    };
}
```

---

## Step 4 — VisaApplication model helper methods

Add these methods to `app/Domain/Applications/Models/VisaApplication.php`
if they do not already exist:

```php
// Accepted documents count for this application
public function acceptedDocumentsCount(): int
{
    return $this->applicationDocuments
        ->where('status', 'accepted')
        ->count();
}

// Required documents count from visa type requirements
public function requiredDocumentsCount(): int
{
    return $this->visaType
        ->documentRequirements()
        ->where('is_required', true)
        ->count();
}

// Workflow progress as a percentage (for the progress bar)
// Defined as: position of current status in the workflow / total steps
public function workflowProgressPercent(): int
{
    $positions = [
        'draft'                  => 5,
        'submitted'              => 20,
        'payment_pending'        => 25,
        'paid'                   => 30,
        'under_review'           => 50,
        'info_requested'         => 50,
        'resubmitted'            => 55,
        'document_verification'  => 65,
        'interview_scheduled'    => 75,
        'decision_pending'       => 85,
        'approved'               => 100,
        'rejected'               => 100,
        'closed'                 => 100,
        'withdrawn'              => 100,
    ];
    return $positions[$this->status] ?? 0;
}

// Colour for the progress bar fill
public function workflowProgressColour(): string
{
    return match($this->status) {
        'approved'       => 'bg-green-500',
        'rejected'       => 'bg-red-500',
        'info_requested' => 'bg-amber-500',
        default          => 'bg-blue-500',
    };
}

// Progress bar label (the current workflow stage name)
public function workflowProgressLabel(): string
{
    return match($this->status) {
        'draft'                 => 'Draft',
        'submitted',
        'payment_pending'       => 'Payment pending',
        'paid'                  => 'Paid',
        'under_review'          => 'Under review',
        'info_requested'        => 'Action required',
        'resubmitted'           => 'Resubmitted',
        'document_verification' => 'Document review',
        'interview_scheduled'   => 'Appointment scheduled',
        'decision_pending'      => 'Officer decision pending',
        'approved'              => 'Complete',
        'rejected'              => 'Complete',
        default                 => ucfirst(str_replace('_', ' ', $this->status)),
    };
}

// Latest rejected document name for the action tag
public function latestRejectedDocumentName(): ?string
{
    $doc = $this->applicationDocuments
        ->where('status', 'rejected')
        ->sortByDesc('updated_at')
        ->first();
    return $doc?->documentType?->name;
}
```

---

## Step 5 — Blade templates

### `resources/views/livewire/applicant-dashboard.blade.php`

```blade
<div class="min-h-screen bg-gray-50 dark:bg-gray-900">

    {{-- Navigation --}}
    <nav class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="flex items-center justify-between h-14">
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-md bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                            <i class="ti ti-id-badge text-blue-600 dark:text-blue-300 text-sm" aria-hidden="true"></i>
                        </div>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">VisaApp</span>
                    </div>
                    <a href="{{ route('dashboard') }}"
                       class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                        My applications
                    </a>
                    <a href="{{ route('documents.index') }}"
                       class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                        Documents
                    </a>
                </div>
                <div class="flex items-center gap-4">
                    @livewire('notification-bell')
                    <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-xs font-medium text-blue-700 dark:text-blue-300">
                        {{ auth()->user()->initials() }}
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-6">

        {{-- Alert bar: action required --}}
        @if($actionRequiredApp)
            <x-alert-bar :application="$actionRequiredApp" class="mb-5" />
        @endif

        {{-- Page header --}}
        <div class="flex items-center justify-between mb-5">
            <h1 class="text-lg font-medium text-gray-900 dark:text-white">
                My applications
            </h1>
            <a href="{{ route('applications.create') }}"
               class="inline-flex items-center gap-1.5 text-sm px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                <i class="ti ti-plus text-sm" aria-hidden="true"></i>
                New application
            </a>
        </div>

        {{-- Stats row --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            <x-stat-card value="{{ $totalCount }}" label="Total" />
            <x-stat-card value="{{ $inProgressCount }}" label="In progress" />
            <x-stat-card
                value="{{ $actionNeededCount }}"
                label="Action needed"
                :highlight="$actionNeededCount > 0"
                highlight-colour="text-amber-600 dark:text-amber-400" />
            <x-stat-card
                value="{{ $approvedCount }}"
                label="Approved"
                :highlight="$approvedCount > 0"
                highlight-colour="text-green-600 dark:text-green-400" />
        </div>

        {{-- Two column layout --}}
        <div class="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-5">

            {{-- Applications list --}}
            <div>
                <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-3">
                    Applications
                </p>

                @forelse($applications as $application)
                    <x-application-card :application="$application" class="mb-2.5" />
                @empty
                    <x-empty-state
                        icon="ti-files"
                        heading="No applications yet"
                        description="Start your first visa application to get going."
                    >
                        <x-slot name="cta">
                            <a href="{{ route('applications.create') }}"
                               class="inline-flex items-center gap-1.5 text-sm px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">
                                <i class="ti ti-plus" aria-hidden="true"></i>
                                New application
                            </a>
                        </x-slot>
                    </x-empty-state>
                @endforelse
            </div>

            {{-- Right sidebar --}}
            <div>
                {{-- Notifications --}}
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl mb-3">
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                        <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-widest">
                            Notifications
                        </p>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse(auth()->user()->notifications()->latest()->take(4)->get() as $notification)
                            <div class="flex items-start gap-2.5 px-4 py-3">
                                <div class="w-1.5 h-1.5 rounded-full mt-1.5 flex-shrink-0
                                    {{ $notification->read_at ? 'bg-gray-300 dark:bg-gray-600' : 'bg-blue-500' }}">
                                </div>
                                <div>
                                    <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">
                                        {{ $notification->data['message'] ?? '' }}
                                    </p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                        {{ $notification->created_at->diffForHumans() }}
                                    </p>
                                </div>
                            </div>
                        @empty
                            <div class="px-4 py-6 text-center text-xs text-gray-400 dark:text-gray-500">
                                No notifications yet
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Quick actions --}}
                <div>
                    <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">
                        Quick actions
                    </p>
                    <x-quick-actions :actions="$quickActions" />
                </div>
            </div>

        </div>
    </div>
</div>
```

### `resources/views/components/stat-card.blade.php`

```blade
@props([
    'value',
    'label',
    'highlight' => false,
    'highlightColour' => 'text-gray-900 dark:text-white',
])

<div {{ $attributes->merge(['class' => 'bg-gray-100 dark:bg-gray-800 rounded-xl p-3.5']) }}>
    <p class="text-2xl font-medium {{ $highlight ? $highlightColour : 'text-gray-900 dark:text-white' }} mb-0.5">
        {{ $value }}
    </p>
    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</p>
</div>
```

### `resources/views/components/application-card.blade.php`

```blade
@props(['application'])

<a href="{{ route('applications.show', $application->id) }}"
   class="block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-3.5 hover:border-gray-300 dark:hover:border-gray-600 transition-colors">

    {{-- Top row: title + badge --}}
    <div class="flex items-start justify-between gap-3 mb-2">
        <div>
            <p class="text-sm font-medium text-gray-900 dark:text-white">
                {{ $application->visaType->name }} — {{ $application->destinationCountry->name }}
            </p>
            <p class="text-xs text-gray-400 dark:text-gray-500 font-mono mt-0.5">
                {{ $application->tracking_number }}
            </p>
        </div>
        <x-status-badge :status="$application->status" />
    </div>

    {{-- Meta row --}}
    <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400 mb-2.5">
        @if($application->submitted_at)
            <span class="flex items-center gap-1">
                <i class="ti ti-calendar text-xs" aria-hidden="true"></i>
                Submitted {{ $application->submitted_at->format('j M Y') }}
            </span>
        @endif

        @if($application->invoice)
            <span class="flex items-center gap-1">
                <i class="ti ti-coin text-xs" aria-hidden="true"></i>
                {{ $application->invoice->formatted_total }} paid
            </span>
        @endif

        @if($application->submitted_at)
            <span class="flex items-center gap-1">
                <i class="ti ti-files text-xs" aria-hidden="true"></i>
                {{ $application->acceptedDocumentsCount() }} / {{ $application->requiredDocumentsCount() }} docs
            </span>
        @endif
    </div>

    {{-- Action tag (only when doc rejected) --}}
    @if($rejectionReason = $application->latestRejectedDocumentName())
        <x-action-tag :message="$rejectionReason . ' — resubmission required'" class="mb-2.5" />
    @endif

    {{-- Progress bar --}}
    <x-progress-bar
        :percent="$application->workflowProgressPercent()"
        :label="$application->workflowProgressLabel()"
        :colour="$application->workflowProgressColour()" />

</a>
```

### `resources/views/components/status-badge.blade.php`

```blade
@props(['status'])

@php
    $enum = App\Domain\Applications\Enums\ApplicationStatus::tryFrom($status);
    $label = $enum?->publicLabel() ?? ucfirst(str_replace('_', ' ', $status));
    $colour = match($enum?->colour()) {
        'green'  => 'bg-green-50 text-green-700 dark:bg-green-900 dark:text-green-300',
        'red'    => 'bg-red-50 text-red-700 dark:bg-red-900 dark:text-red-300',
        'amber'  => 'bg-amber-50 text-amber-700 dark:bg-amber-900 dark:text-amber-300',
        'blue'   => 'bg-blue-50 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
        'purple' => 'bg-purple-50 text-purple-700 dark:bg-purple-900 dark:text-purple-300',
        default  => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full $colour"]) }}>
    {{ $label }}
</span>
```

### `resources/views/components/progress-bar.blade.php`

```blade
@props(['percent', 'label', 'colour' => 'bg-blue-500'])

<div class="flex items-center gap-2">
    <div class="flex-1 h-1 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
        <div class="h-1 rounded-full {{ $colour }}" style="width: {{ $percent }}%"></div>
    </div>
    <span class="text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap">{{ $label }}</span>
</div>
```

### `resources/views/components/alert-bar.blade.php`

```blade
@props(['application'])

<div {{ $attributes->merge(['class' => 'flex items-start gap-2.5 px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-950 border border-amber-200 dark:border-amber-800']) }}>
    <i class="ti ti-alert-triangle text-amber-600 dark:text-amber-400 text-sm flex-shrink-0 mt-0.5" aria-hidden="true"></i>
    <span class="text-sm text-amber-800 dark:text-amber-300">
        <strong>Action required</strong> on
        <span class="font-mono">{{ $application->tracking_number }}</span>
        @if($docName = $application->latestRejectedDocumentName())
            — your {{ $docName }} was rejected. Please resubmit.
        @else
            — additional information requested.
        @endif
    </span>
</div>
```

### `resources/views/components/action-tag.blade.php`

```blade
@props(['message'])

@if($message)
    <div {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 text-xs text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950 px-2 py-1 rounded-full']) }}>
        <i class="ti ti-alert-triangle text-xs" aria-hidden="true"></i>
        {{ $message }}
    </div>
@endif
```

### `resources/views/components/quick-actions.blade.php`

```blade
@props(['actions' => []])

<div class="flex flex-col gap-2">
    @foreach($actions as $action)
        <a href="{{ $action['href'] }}"
           class="flex items-center gap-2 text-sm px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <i class="ti ti-{{ $action['icon'] }} text-sm" aria-hidden="true"></i>
            {{ $action['label'] }}
        </a>
    @endforeach
</div>
```

### `resources/views/livewire/notification-bell.blade.php`

```blade
<div class="relative">
    <button wire:click="togglePanel"
            class="relative p-1.5 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
            aria-label="Notifications">
        <i class="ti ti-bell text-lg" aria-hidden="true"></i>
        @if($unreadCount > 0)
            <span class="absolute top-0.5 right-0.5 w-2 h-2 bg-red-500 rounded-full border-2 border-white dark:border-gray-800"></span>
        @endif
    </button>

    @if($open)
        <div class="absolute right-0 top-10 w-80 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg z-50">
            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                <span class="text-sm font-medium text-gray-900 dark:text-white">Notifications</span>
                <button wire:click="togglePanel" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <i class="ti ti-x text-sm" aria-hidden="true"></i>
                </button>
            </div>
            <div class="max-h-72 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($notifications as $n)
                    <div class="flex items-start gap-2.5 px-4 py-3">
                        <div class="w-1.5 h-1.5 rounded-full mt-1.5 flex-shrink-0
                            {{ $n->read_at ? 'bg-gray-200 dark:bg-gray-600' : 'bg-blue-500' }}">
                        </div>
                        <div>
                            <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">
                                {{ $n->data['message'] ?? '' }}
                            </p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ $n->created_at->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-xs text-gray-400">
                        No notifications
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Close on click outside --}}
        <div wire:click="togglePanel" class="fixed inset-0 z-40"></div>
    @endif
</div>
```

---

## Step 6 — User model helper method

Add to `app/Domain/Identity/Models/User.php` if not present:

```php
public function initials(): string
{
    $parts = explode(' ', trim($this->name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts)-1], 0, 1));
    }
    return strtoupper(substr($this->name, 0, 2));
}
```

---

## Step 7 — Invoice formatted_total accessor

Add to `app/Domain/Payments/Models/Invoice.php` if not present:

```php
public function getFormattedTotalAttribute(): string
{
    $symbols = ['INR' => '₹', 'USD' => '$', 'GBP' => '£', 'EUR' => '€'];
    $symbol = $symbols[$this->currency] ?? $this->currency . ' ';
    return $symbol . number_format($this->total, 0);
}
```

---

## Step 8 — Policy guard

Ensure `app/Domain/Applications/Policies/VisaApplicationPolicy.php`
has a method that prevents cross-user access:

```php
public function viewOwn(User $user, VisaApplication $application): bool
{
    return $application->user_id === $user->id;
}
```

The Livewire component's query already scopes to `user_id = auth()->id()`.
This policy method is for the route model binding on the show/respond routes.

---

## Acceptance criteria — all must pass before done

### Feature tests

```php
// tests/Feature/ApplicantDashboardTest.php

it('renders dashboard for authenticated verified applicant', function () {
    $user = User::factory()->create()->assignRole('applicant');
    actingAs($user)->get(route('dashboard'))->assertOk();
});

it('unauthenticated user is redirected to login', function () {
    get(route('dashboard'))->assertRedirect(route('login'));
});

it('unverified user is redirected to email verification', function () {
    $user = User::factory()->unverified()->create()->assignRole('applicant');
    actingAs($user)->get(route('dashboard'))->assertRedirect(route('verification.notice'));
});

it('applicant only sees their own applications', function () {
    $userA = User::factory()->create()->assignRole('applicant');
    $userB = User::factory()->create()->assignRole('applicant');
    $appA = VisaApplication::factory()->for($userA)->create();
    $appB = VisaApplication::factory()->for($userB)->create();

    $component = Livewire::actingAs($userA)->test(ApplicantDashboard::class);
    $component->assertSee($appA->tracking_number)
              ->assertDontSee($appB->tracking_number);
});

it('alert bar is visible when info_requested application exists', function () {
    $user = User::factory()->create()->assignRole('applicant');
    VisaApplication::factory()->for($user)->infoRequested()->create();

    Livewire::actingAs($user)
        ->test(ApplicantDashboard::class)
        ->assertSet('actionRequiredApp', fn($v) => $v !== null);
});

it('alert bar is hidden when no info_requested applications exist', function () {
    $user = User::factory()->create()->assignRole('applicant');

    Livewire::actingAs($user)
        ->test(ApplicantDashboard::class)
        ->assertSet('actionRequiredApp', null);
});

it('quick actions includes resubmit only when info_requested exists', function () {
    $user = User::factory()->create()->assignRole('applicant');
    VisaApplication::factory()->for($user)->infoRequested()->create();

    $component = Livewire::actingAs($user)->test(ApplicantDashboard::class);
    $actions = $component->get('quickActions');
    $labels = collect($actions)->pluck('label')->toArray();

    expect($labels)->toContain('Resubmit documents');
});

it('stat counts are correct', function () {
    $user = User::factory()->create()->assignRole('applicant');
    VisaApplication::factory()->for($user)->approved()->create();
    VisaApplication::factory()->for($user)->underReview()->create();

    $component = Livewire::actingAs($user)->test(ApplicantDashboard::class);
    $component->assertSet('totalCount', 2)
              ->assertSet('approvedCount', 1)
              ->assertSet('inProgressCount', 1);
});
```

### UI acceptance

- [ ] GET /dashboard returns 200 for authenticated verified applicant
- [ ] GET /dashboard redirects to /login for unauthenticated request
- [ ] Stats row shows correct counts from Eloquent (not hardcoded)
- [ ] Alert bar only visible when an application has status info_requested
- [ ] Application cards show tracking number in font-mono class
- [ ] Status badge colour matches ApplicationStatus enum colour() method
- [ ] Progress bar width is computed, not hardcoded
- [ ] Action tag only appears on cards with a rejected document
- [ ] Quick actions: resubmit only shown when info_requested application exists
- [ ] Quick actions: download only shown when approved application exists
- [ ] Empty state shown when applicant has no applications
- [ ] Notification bell unread dot only visible when unreadCount > 0
- [ ] Clicking bell marks notifications as read and hides the dot
- [ ] Dark mode: all colours use CSS variables or Tailwind dark: prefix
- [ ] php artisan test passes
- [ ] php artisan pint --test passes

---

## After completing

1. Run: `php artisan test`
2. Run: `php artisan pint --test`
3. List every file created or modified
4. Confirm all acceptance criteria above are satisfied
5. Note any assumptions (add TODO comments in code)

## What NOT to build in this task

- Application wizard (separate Livewire component, later milestone)
- Document upload panel (separate milestone)
- Payment pages (separate milestone)
- Public tracking page (separate route)
- Officer or admin panel changes
- Email notifications (milestone 6)
