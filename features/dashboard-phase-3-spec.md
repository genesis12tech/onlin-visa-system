# Dashboard UI — Phase 3 Spec (Filament 4 / Laravel 12)

## Overview

Phase 3 of 3. Fills in the main content area of the Officer Dashboard with all visible
widgets from the screenshot: the greeting header with CTA, four stat cards, the Priority
Queue table, the Team Workload sidebar panel, and Today's Activity feed. All data comes
from `database/mock/officer-data.php` via `App\Support\MockOfficerData` (built in Phase 2).

---

## Reference

Visual target: `docs/screenshots/officer-dashboard-ui/dashboard-main.jpg`

Main area breakdown from screenshot:
1. **Header** — "Good morning, Priya 👋", date + pending count subtitle, "Open Review Queue →" button
2. **Four stat cards** — Assigned to Me, Approved Today, Pending Queue, Avg. Decision Time
3. **Priority Queue table** — columns: Priority, Reference, Applicant, Type, Days, Action
4. **Right column** — Team Workload widget + Today's Activity feed

---

## Architecture Decisions

- Each visual block is its own **Filament Widget** class. This keeps them independently
  sortable, testable, and replaceable with live data later.
- The Dashboard page class orchestrates layout via `getWidgets()` and `getColumns()`.
- Widgets pull from `MockOfficerData` in `mount()` / `getStats()` / `getTableQuery()`.
- No Livewire polling is added yet — static render is sufficient for the mock phase.

---

## Requirements

### 1. Dashboard Page — Layout Update

Update `app/Filament/Officer/Pages/Dashboard.php` to register all widgets and set
the 12-column grid that matches the screenshot layout:

```php
<?php

namespace App\Filament\Officer\Pages;

use App\Filament\Officer\Widgets\DashboardHeader;
use App\Filament\Officer\Widgets\StatsOverview;
use App\Filament\Officer\Widgets\PriorityQueueTable;
use App\Filament\Officer\Widgets\TeamWorkload;
use App\Filament\Officer\Widgets\TodaysActivity;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon  = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?string $title           = '';   // title rendered by DashboardHeader widget
    protected static ?int    $navigationSort  = 1;
    protected static string  $view            = 'filament.officer.pages.dashboard';

    public function getColumns(): int|string|array
    {
        return [
            'default' => 1,
            'md'      => 4,    // 4-column grid: 3 cols for main, 1 for right panel
        ];
    }

    public function getWidgets(): array
    {
        return [
            DashboardHeader::class,
            StatsOverview::class,
            PriorityQueueTable::class,
            TeamWorkload::class,
            TodaysActivity::class,
        ];
    }
}
```

---

### 2. Dashboard Blade View

Replace the Phase 1 placeholder view.

File: `resources/views/filament/officer/pages/dashboard.blade.php`

```blade
<x-filament-panels::page>
    {{-- Full-width header --}}
    @livewire(\App\Filament\Officer\Widgets\DashboardHeader::class)

    {{-- Stat cards — full width --}}
    @livewire(\App\Filament\Officer\Widgets\StatsOverview::class)

    {{-- Two-column area: Priority Queue (left) + right panel (Team + Activity) --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        <div class="xl:col-span-2">
            @livewire(\App\Filament\Officer\Widgets\PriorityQueueTable::class)
        </div>

        <div class="flex flex-col gap-6">
            @livewire(\App\Filament\Officer\Widgets\TeamWorkload::class)
            @livewire(\App\Filament\Officer\Widgets\TodaysActivity::class)
        </div>

    </div>
</x-filament-panels::page>
```

---

### 3. Widget — DashboardHeader

Renders the greeting, subtitle, and the "Open Review Queue" CTA button.

File: `app/Filament/Officer/Widgets/DashboardHeader.php`

```php
<?php

namespace App\Filament\Officer\Widgets;

use App\Support\MockOfficerData;
use Filament\Widgets\Widget;

class DashboardHeader extends Widget
{
    protected static string $view = 'filament.officer.widgets.dashboard-header';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public string $officerName   = '';
    public int    $pendingCount  = 0;
    public string $todayLabel    = '';

    public function mount(): void
    {
        $data = MockOfficerData::load();

        $this->officerName  = $data['officer']['name']       ?? auth()->user()?->name ?? 'Officer';
        $this->pendingCount = $data['pending_queue_count']   ?? 12;
        $this->todayLabel   = now()->format('l, j F Y');
    }
}
```

Blade view: `resources/views/filament/officer/widgets/dashboard-header.blade.php`

```blade
<x-filament-widgets::widget>
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-white">
                Good morning, {{ $officerName }} 👋
            </h1>
            <p class="mt-1 text-sm text-gray-400">
                {{ $todayLabel }} · You have {{ $pendingCount }} applications awaiting review
            </p>
        </div>
        <a href="{{ route('filament.officer.pages.review-queue') }}"
           class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 transition">
            Open Review Queue →
        </a>
    </div>
</x-filament-widgets::widget>
```

---

### 4. Widget — StatsOverview (Four Stat Cards)

Uses Filament's built-in `StatsOverviewWidget` for the four top cards.

File: `app/Filament/Officer/Widgets/StatsOverview.php`

```php
<?php

namespace App\Filament\Officer\Widgets;

use App\Support\MockOfficerData;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $data = MockOfficerData::load();

        return [
            Stat::make('Assigned to Me', $data['assigned_to_me'] ?? 8)
                ->description('Active cases')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray'),

            Stat::make('Approved Today', $data['approved_today'] ?? 4)
                ->description('+2 vs yesterday')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->icon('heroicon-o-check-badge')
                ->color('success'),

            Stat::make('Pending Queue', $data['pending_queue_count'] ?? 12)
                ->description('Awaiting review')
                ->icon('heroicon-o-clock')
                ->color('warning'),

            Stat::make('Avg. Decision Time', ($data['avg_decision_days'] ?? 2.4) . 'd')
                ->description('0.3d faster')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->icon('heroicon-o-bolt')
                ->color('info'),
        ];
    }
}
```

---

### 5. Widget — PriorityQueueTable

Uses Filament's `TableWidget` to render the priority queue table.

File: `app/Filament/Officer/Widgets/PriorityQueueTable.php`

```php
<?php

namespace App\Filament\Officer\Widgets;

use App\Support\MockOfficerData;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Collection;

class PriorityQueueTable extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;

    protected static ?string $heading = 'Priority Queue';

    protected static ?string $pollingInterval = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // Swap for real Eloquent query in Phase 5
                \App\Domain\Applications\Models\VisaApplication::query()
                    ->whereNull('id') // returns empty; mock fills records below
            )
            ->records(fn () => collect(MockOfficerData::get('priority_queue', [])))
            ->columns([
                Tables\Columns\IconColumn::make('priority_flag')
                    ->label('Priority')
                    ->icon(fn ($record) => 'heroicon-s-circle')
                    ->color(fn ($record) => match ($record['priority'] ?? 'low') {
                        'high'   => 'danger',
                        'medium' => 'warning',
                        default  => 'success',
                    }),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Reference')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('applicant')
                    ->label('Applicant'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type'),

                Tables\Columns\BadgeColumn::make('days_pending')
                    ->label('Days')
                    ->color(fn ($state) => $state >= 7 ? 'danger' : 'success')
                    ->formatStateUsing(fn ($state) => $state . 'd'),

                Tables\Columns\TextColumn::make('action')
                    ->label('')
                    ->default('Review')
                    ->badge()
                    ->color('gray'),
            ])
            ->paginated(false)
            ->striped(false);
    }
}
```

> **Note on `->records()`**: Filament 4's `TableWidget` supports passing a `Collection`
> directly when there is no Eloquent model yet. Once the database is live in Phase 5,
> replace `->records(...)` with `->query(VisaApplication::query()->pending()->oldest())`.

---

### 6. Widget — TeamWorkload

A custom widget rendering the right-hand team workload panel.

File: `app/Filament/Officer/Widgets/TeamWorkload.php`

```php
<?php

namespace App\Filament\Officer\Widgets;

use App\Support\MockOfficerData;
use Filament\Widgets\Widget;

class TeamWorkload extends Widget
{
    protected static string $view = 'filament.officer.widgets.team-workload';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 4;

    public array $members = [];

    public function mount(): void
    {
        $this->members = MockOfficerData::get('team_workload', []);
    }
}
```

Blade view: `resources/views/filament/officer/widgets/team-workload.blade.php`

```blade
<x-filament-widgets::widget>
    <x-filament::section heading="Team Workload">
        <ul class="space-y-4">
            @foreach ($members as $member)
                <li class="flex items-center gap-3">
                    {{-- Avatar circle --}}
                    <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-gray-700 text-xs font-bold text-white">
                        {{ $member['initials'] }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-white">{{ $member['name'] }}</p>
                        <p class="truncate text-xs text-gray-400">{{ $member['specialisations'] }}</p>
                    </div>
                    {{-- Progress bar --}}
                    <div class="w-24">
                        <div class="h-1.5 rounded-full bg-gray-700">
                            <div class="h-1.5 rounded-full {{ $member['bar_color'] ?? 'bg-green-500' }}"
                                 style="width: {{ min(100, ($member['assigned'] / $member['capacity']) * 100) }}%">
                            </div>
                        </div>
                    </div>
                    <span class="text-xs text-gray-400 tabular-nums">
                        {{ $member['assigned'] }}/{{ $member['capacity'] }}
                    </span>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
```

---

### 7. Widget — TodaysActivity

A custom widget rendering the activity feed at the bottom of the right column.

File: `app/Filament/Officer/Widgets/TodaysActivity.php`

```php
<?php

namespace App\Filament\Officer\Widgets;

use App\Support\MockOfficerData;
use Filament\Widgets\Widget;

class TodaysActivity extends Widget
{
    protected static string $view = 'filament.officer.widgets.todays-activity';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 5;

    public array $activities = [];

    public function mount(): void
    {
        $this->activities = MockOfficerData::get('todays_activity', []);
    }
}
```

Blade view: `resources/views/filament/officer/widgets/todays-activity.blade.php`

```blade
<x-filament-widgets::widget>
    <x-filament::section heading="Today's Activity">
        <ul class="space-y-4">
            @foreach ($activities as $event)
                <li class="flex items-start gap-3">
                    <div class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full
                        {{ match($event['type'] ?? 'default') {
                            'approved' => 'bg-green-500/20',
                            'requested' => 'bg-yellow-500/20',
                            default => 'bg-gray-700',
                        } }}">
                        @if (($event['type'] ?? '') === 'approved')
                            <x-heroicon-s-check class="h-3 w-3 text-green-400" />
                        @elseif (($event['type'] ?? '') === 'requested')
                            <x-heroicon-s-exclamation-triangle class="h-3 w-3 text-yellow-400" />
                        @else
                            <x-heroicon-s-arrow-up-right class="h-3 w-3 text-gray-400" />
                        @endif
                    </div>
                    <div>
                        <p class="text-sm text-white">{{ $event['description'] }}</p>
                        <p class="text-xs text-gray-500">{{ $event['ago'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
```

---

### 8. Mock Data Shape

`database/mock/officer-data.php` must include at least the following keys for Phase 3
widgets to render correctly. Add these if not already present:

```php
<?php

return [
    'officer' => [
        'name'      => 'Priya',
        'full_name' => 'Priya Mehta',
        'role'      => 'Senior Visa Officer',
        'id'        => 'OFF-001',
    ],

    'assigned_to_me'      => 8,
    'approved_today'      => 4,
    'pending_queue_count' => 12,
    'avg_decision_days'   => 2.4,

    'priority_queue' => [
        ['priority' => 'low',  'reference' => 'VA-2024-A1F3K2', 'applicant' => 'Arjun Mehta',   'type' => 'Tourist',  'days_pending' => 2],
        ['priority' => 'high', 'reference' => 'VA-2024-C3H5M4', 'applicant' => 'James Okonkwo', 'type' => 'Work',     'days_pending' => 7],
        ['priority' => 'low',  'reference' => 'VA-2024-D4I6N5', 'applicant' => 'Maria Santos',  'type' => 'Business', 'days_pending' => 1],
        ['priority' => 'low',  'reference' => 'VA-2024-E5J706', 'applicant' => 'Ahmed Al-Rashid','type' => 'Tourist', 'days_pending' => 2],
    ],

    'team_workload' => [
        ['initials' => 'PM', 'name' => 'Priya Mehta',     'specialisations' => 'Tourist & Business', 'assigned' => 8,  'capacity' => 12, 'bar_color' => 'bg-yellow-400'],
        ['initials' => 'RS', 'name' => 'Rahul Sharma',    'specialisations' => 'Work & Student',      'assigned' => 6,  'capacity' => 12, 'bar_color' => 'bg-green-400'],
        ['initials' => 'AD', 'name' => 'Anita Desai',     'specialisations' => 'Medical & Transit',   'assigned' => 10, 'capacity' => 12, 'bar_color' => 'bg-red-400'],
        ['initials' => 'MK', 'name' => 'Mohammed Khan',   'specialisations' => 'Business & Work',     'assigned' => 4,  'capacity' => 12, 'bar_color' => 'bg-green-400'],
    ],

    'todays_activity' => [
        ['type' => 'approved',  'description' => 'Approved VA-2024-G7L9Q8 (Lucas Müller)', 'ago' => '2h ago'],
        ['type' => 'requested', 'description' => 'Requested docs for VA-2024-C3H5M4',      'ago' => '4h ago'],
        ['type' => 'assigned',  'description' => 'Assigned VA-2024-B2G4L3 to self',        'ago' => '5h ago'],
    ],
];
```

---

## Acceptance Criteria

- [ ] Dashboard loads at `/officer` showing the full layout from the screenshot.
- [ ] Greeting shows the correct officer name and today's date.
- [ ] "Open Review Queue →" button links to `/officer/review-queue`.
- [ ] Four stat cards display correct mock values.
- [ ] Priority Queue table shows 4 rows; the 7-day row has a red badge.
- [ ] Team Workload panel shows 4 officers with progress bars.
- [ ] Today's Activity panel shows 3 events with correct icons.
- [ ] Layout is two-column on `xl:` screens (queue left, right panel right).
- [ ] Layout stacks single-column on mobile.
- [ ] `php artisan test` passes.
- [ ] `php artisan pint --test` passes.

---

## Files Created / Modified

| Action | Path |
|--------|------|
| Modify | `app/Filament/Officer/Pages/Dashboard.php` |
| Modify | `resources/views/filament/officer/pages/dashboard.blade.php` |
| Create | `app/Filament/Officer/Widgets/DashboardHeader.php` |
| Create | `app/Filament/Officer/Widgets/StatsOverview.php` |
| Create | `app/Filament/Officer/Widgets/PriorityQueueTable.php` |
| Create | `app/Filament/Officer/Widgets/TeamWorkload.php` |
| Create | `app/Filament/Officer/Widgets/TodaysActivity.php` |
| Create | `resources/views/filament/officer/widgets/dashboard-header.blade.php` |
| Create | `resources/views/filament/officer/widgets/team-workload.blade.php` |
| Create | `resources/views/filament/officer/widgets/todays-activity.blade.php` |
| Modify | `database/mock/officer-data.php` (add missing keys per spec) |

---

## What Is Explicitly Deferred

- Eloquent queries replacing mock data → Phase 5
- Livewire real-time polling / push updates → Phase 6
- "Open Review Queue" deep-link to a filtered resource view → Phase 5
- Clickable reference numbers linking to `VisaApplicationResource` → Phase 5
