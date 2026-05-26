# Dashboard UI — Phase 2 Spec (Filament 4 / Laravel 12)

## Overview

Phase 2 of 3. Replaces the Phase 1 sidebar placeholder with a fully-wired Filament 4
navigation sidebar: collapsible, mobile-drawer behaviour, workspace/tools navigation
groups, reporting links, and a user avatar area at the bottom. Mock data is imported
directly from `database/mock/officer-data.php` — no database layer yet.

---

## Reference

Visual target: `docs/screenshots/officer-dashboard-ui/dashboard-main.jpg`

Sidebar groups visible in screenshot:
- **WORKSPACE** → Dashboard, Review Queue (badge 12), My Assigned (badge 8), All Applications
- **TOOLS** → Document Review (badge 5), Search
- **REPORTING** → My Performance, Reports
- **Bottom** → Officer avatar + name + role

---

## How Filament 4 Navigation Works

Filament 4 builds the sidebar from the navigation items registered on each Page and
Resource. There is **no separate sidebar component to create**. The sidebar is:

- Collapsed/expanded via `->sidebarCollapsibleOnDesktop()` or
  `->sidebarFullyCollapsibleOnDesktop()` on the panel.
- Always rendered as a drawer on mobile automatically.
- Grouped with `->navigationGroup(...)` on each page/resource class.
- The user avatar area is provided by the panel's `->userMenuItems(...)` and the
  built-in `UserMenuItem` component.

---

## Requirements

### 1. Enable Collapsible Sidebar

Update `app/Providers/Filament/OfficerPanelProvider.php`:

```php
return $panel
    // ... existing config ...
    ->sidebarCollapsibleOnDesktop()   // collapses to icon-only rail on desktop
    // On mobile Filament renders a drawer automatically — no extra config needed
    ->navigationGroups([
        NavigationGroup::make('Workspace')
            ->label('WORKSPACE'),
        NavigationGroup::make('Tools')
            ->label('TOOLS'),
        NavigationGroup::make('Reporting')
            ->label('REPORTING'),
    ]);
```

Add imports:

```php
use Filament\Navigation\NavigationGroup;
```

---

### 2. Navigation Pages

Create one lightweight Page class per sidebar link. Each class declares its group,
icon, label, sort order, and badge (where applicable).

#### 2a. Review Queue

File: `app/Filament/Officer/Pages/ReviewQueue.php`

```php
<?php

namespace App\Filament\Officer\Pages;

use Filament\Pages\Page;

class ReviewQueue extends Page
{
    protected static ?string $navigationGroup   = 'Workspace';
    protected static ?string $navigationIcon    = 'heroicon-o-inbox-stack';
    protected static ?string $navigationLabel   = 'Review Queue';
    protected static ?int    $navigationSort    = 2;
    protected static string  $view              = 'filament.officer.pages.review-queue';

    public static function getNavigationBadge(): ?string
    {
        // Replace with real query in Phase 5
        return (string) (app(\App\Domain\Applications\Queries\PendingQueueCount::class)->count());
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
```

> For now the query class can return the mock value directly (see mock data section).

#### 2b. My Assigned

File: `app/Filament/Officer/Pages/MyAssigned.php`

```php
<?php

namespace App\Filament\Officer\Pages;

use Filament\Pages\Page;

class MyAssigned extends Page
{
    protected static ?string $navigationGroup = 'Workspace';
    protected static ?string $navigationIcon  = 'heroicon-o-user-circle';
    protected static ?string $navigationLabel = 'My Assigned';
    protected static ?int    $navigationSort  = 3;
    protected static string  $view            = 'filament.officer.pages.my-assigned';

    public static function getNavigationBadge(): ?string
    {
        return '8'; // mock — replace in Phase 5
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }
}
```

#### 2c. All Applications

File: `app/Filament/Officer/Pages/AllApplications.php`

```php
<?php

namespace App\Filament\Officer\Pages;

use Filament\Pages\Page;

class AllApplications extends Page
{
    protected static ?string $navigationGroup = 'Workspace';
    protected static ?string $navigationIcon  = 'heroicon-o-list-bullet';
    protected static ?string $navigationLabel = 'All Applications';
    protected static ?int    $navigationSort  = 4;
    protected static string  $view            = 'filament.officer.pages.all-applications';
}
```

#### 2d. Document Review

File: `app/Filament/Officer/Pages/DocumentReview.php`

```php
<?php

namespace App\Filament\Officer\Pages;

use Filament\Pages\Page;

class DocumentReview extends Page
{
    protected static ?string $navigationGroup = 'Tools';
    protected static ?string $navigationIcon  = 'heroicon-o-document-magnifying-glass';
    protected static ?string $navigationLabel = 'Document Review';
    protected static ?int    $navigationSort  = 1;
    protected static string  $view            = 'filament.officer.pages.document-review';

    public static function getNavigationBadge(): ?string
    {
        return '5'; // mock
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
```

#### 2e. Search

File: `app/Filament/Officer/Pages/SearchPage.php`

```php
<?php

namespace App\Filament\Officer\Pages;

use Filament\Pages\Page;

class SearchPage extends Page
{
    protected static ?string $navigationGroup = 'Tools';
    protected static ?string $navigationIcon  = 'heroicon-o-magnifying-glass';
    protected static ?string $navigationLabel = 'Search';
    protected static ?int    $navigationSort  = 2;
    protected static string  $view            = 'filament.officer.pages.search';
}
```

#### 2f. My Performance

File: `app/Filament/Officer/Pages/MyPerformance.php`

```php
<?php

namespace App\Filament\Officer\Pages;

use Filament\Pages\Page;

class MyPerformance extends Page
{
    protected static ?string $navigationGroup = 'Reporting';
    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'My Performance';
    protected static ?int    $navigationSort  = 1;
    protected static string  $view            = 'filament.officer.pages.my-performance';
}
```

#### 2g. Reports

File: `app/Filament/Officer/Pages/Reports.php`

```php
<?php

namespace App\Filament\Officer\Pages;

use Filament\Pages\Page;

class Reports extends Page
{
    protected static ?string $navigationGroup = 'Reporting';
    protected static ?string $navigationIcon  = 'heroicon-o-table-cells';
    protected static ?string $navigationLabel = 'Reports';
    protected static ?int    $navigationSort  = 2;
    protected static string  $view            = 'filament.officer.pages.reports';
}
```

Each view file is a simple placeholder Blade file for now:

```blade
{{-- e.g. resources/views/filament/officer/pages/review-queue.blade.php --}}
<x-filament-panels::page>
    <p class="text-gray-400">Review Queue — coming in Phase 5.</p>
</x-filament-panels::page>
```

---

### 3. Register Pages in the Panel

Update `OfficerPanelProvider`:

```php
->discoverPages(
    in: app_path('Filament/Officer/Pages'),
    for: 'App\\Filament\\Officer\\Pages',
)
```

`discoverPages` picks up all classes automatically — no manual `->pages([...])` list
needed (remove the manual array from Phase 1).

---

### 4. User Avatar Area (Bottom of Sidebar)

Filament 4 renders the authenticated user's name and avatar automatically at the
bottom of every panel sidebar. No custom component is needed.

To display the officer's role subtitle, publish and extend the user menu:

In `OfficerPanelProvider`:

```php
->userMenuItems([
    MenuItem::make()
        ->label('Profile')
        ->icon('heroicon-o-user')
        ->url(fn () => route('filament.officer.pages.profile')),
])
```

The avatar is rendered from `auth()->user()->name` and `auth()->user()->getFilamentAvatarUrl()`.
To add the role label beneath the name, override the panel's profile component in
`resources/views/vendor/filament-panels/components/layout/sidebar/user-info.blade.php`
(publish with `php artisan vendor:publish --tag=filament-panels-views`):

```blade
<div class="flex items-center gap-x-3 px-4 py-3">
    <x-filament-panels::avatar.user :user="filament()->auth()->user()" />
    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-medium text-white">
            {{ filament()->auth()->user()->name }}
        </p>
        <p class="truncate text-xs text-gray-400">
            {{ filament()->auth()->user()->getRoleNames()->first() ?? 'Officer' }}
            · {{ filament()->auth()->user()->officer_id ?? 'OFF-001' }}
        </p>
    </div>
</div>
```

---

### 5. Mock Data Import

Until the database is wired up, import mock data from the PHP array directly in the
page classes or a shared service:

File: `app/Support/MockOfficerData.php`

```php
<?php

namespace App\Support;

class MockOfficerData
{
    public static function load(): array
    {
        return require database_path('mock/officer-data.php');
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::load()[$key] ?? $default;
    }
}
```

Usage in any Page `mount()` or widget:

```php
use App\Support\MockOfficerData;

$pendingCount = MockOfficerData::get('pending_queue_count', 12);
```

---

### 6. Mobile Drawer Behaviour

Filament 4's sidebar is **automatically a drawer on mobile** (below the `lg:` breakpoint).
No custom Alpine.js drawer component is needed. To verify:

- Resize the browser below `1024px`.
- The sidebar collapses and a hamburger/menu icon appears in the top bar.
- Tapping it opens the full navigation drawer.

---

## Acceptance Criteria

- [ ] Sidebar shows three labelled groups: WORKSPACE, TOOLS, REPORTING.
- [ ] Review Queue badge shows `12`, My Assigned badge shows `8`, Document Review shows `5`.
- [ ] Clicking each link navigates to the correct placeholder page without a 404.
- [ ] On desktop, the sidebar collapse toggle hides labels and shows only icons.
- [ ] On mobile (< 1024 px) the sidebar is hidden and opens as a drawer.
- [ ] User avatar area at the bottom of the sidebar shows the logged-in officer's name.
- [ ] `php artisan test` passes.
- [ ] `php artisan pint --test` passes.

---

## Files Created / Modified

| Action | Path |
|--------|------|
| Modify | `app/Providers/Filament/OfficerPanelProvider.php` |
| Create | `app/Filament/Officer/Pages/ReviewQueue.php` |
| Create | `app/Filament/Officer/Pages/MyAssigned.php` |
| Create | `app/Filament/Officer/Pages/AllApplications.php` |
| Create | `app/Filament/Officer/Pages/DocumentReview.php` |
| Create | `app/Filament/Officer/Pages/SearchPage.php` |
| Create | `app/Filament/Officer/Pages/MyPerformance.php` |
| Create | `app/Filament/Officer/Pages/Reports.php` |
| Create | `app/Support/MockOfficerData.php` |
| Create | `resources/views/filament/officer/pages/*.blade.php` (one stub per page) |
| Publish (optional) | `resources/views/vendor/filament-panels/components/layout/sidebar/user-info.blade.php` |

---

## What Is Explicitly Deferred

- Auth guard wiring and role-based nav visibility → Phase 5
- Real badge counts from the database → Phase 5
- Main content area widgets (stats cards, priority queue table) → Phase 3
