# Dashboard UI — Phase 1 Spec (Filament 4 / Laravel 12)

## Overview

Phase 1 of 3. Establishes the Officer panel foundation: Filament 4 panel registration,
dark-mode theme, the custom Dashboard page class, and placeholder layout areas for the
sidebar and main content. No real data yet — only structural scaffolding that phases 2
and 3 will fill in.

---

## Reference

Visual target: `docs/screenshots/officer-dashboard-ui/dashboard-main.jpg`

---

## Filament 4 Context

Filament 4 does **not** use ShadCN or any Node-based component library.
All UI is rendered with Blade + Livewire + Tailwind CSS (compiled by Vite via
`@filament/support`). There is no ShadCN installation step. The equivalent of
"component installation" in Filament 4 is publishing and extending the panel theme.

---

## Requirements

### 1. Officer Panel Registration

Register a dedicated Filament panel for officers.

File: `app/Providers/Filament/OfficerPanelProvider.php`

```php
<?php

namespace App\Providers\Filament;

use App\Filament\Officer\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;

class OfficerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('officer')
            ->path('officer')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->darkMode(defaultIsDark: true)   // Dark mode on by default
            ->discoverPages(
                in: app_path('Filament/Officer/Pages'),
                for: 'App\\Filament\\Officer\\Pages',
            )
            ->discoverWidgets(
                in: app_path('Filament/Officer/Widgets'),
                for: 'App\\Filament\\Officer\\Widgets',
            )
            ->pages([Dashboard::class])
            ->widgets([])
            ->middleware([
                // Add auth middleware in Phase 2 when auth is wired up
            ]);
    }
}
```

Register the provider in `bootstrap/providers.php`:

```php
App\Providers\Filament\OfficerPanelProvider::class,
```

---

### 2. Custom Dashboard Page

Override the default dashboard to give us full control over the layout.

File: `app/Filament/Officer/Pages/Dashboard.php`

```php
<?php

namespace App\Filament\Officer\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Dashboard';

    protected static ?int $navigationSort = 1;

    /**
     * Grid columns — will be replaced in Phase 3 with real widget layout.
     * 12-column grid mirrors the screenshot's card + sidebar structure.
     */
    public function getColumns(): int|string|array
    {
        return [
            'default' => 1,
            'md'      => 12,
        ];
    }

    /**
     * Widgets registered here in Phase 3.
     * Returning an empty array disables auto-discovery for now.
     */
    public function getWidgets(): array
    {
        return [];
    }
}
```

---

### 3. Dashboard Route

The panel auto-registers `/officer` as the base path. The dashboard is available at:

```
/officer  (redirects to /officer/dashboard by Filament)
```

No manual route registration is required. Confirm the panel loads by visiting
`/officer` in the browser.

---

### 4. Dark Mode by Default

Dark mode is set panel-wide via `->darkMode(defaultIsDark: true)` in the panel provider
(see step 1). This applies Tailwind's `dark:` variant across all Filament views without
any additional configuration.

---

### 5. Placeholder Layout — Sidebar and Main Area

Add a minimal Blade view to confirm the two-column structure renders.

File: `resources/views/filament/officer/pages/dashboard.blade.php`

```blade
<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-12 gap-6 min-h-screen">

        {{-- Sidebar placeholder (phases 2 will replace this) --}}
        <aside class="md:col-span-2 rounded-xl bg-gray-800 p-4">
            <h2 class="text-white text-lg font-semibold">Sidebar</h2>
        </aside>

        {{-- Main area placeholder (phase 3 will replace this) --}}
        <main class="md:col-span-10 rounded-xl bg-gray-800 p-4">
            <h2 class="text-white text-lg font-semibold">Main</h2>
        </main>

    </div>
</x-filament-panels::page>
```

Point the Dashboard page to this view by adding to `Dashboard.php`:

```php
protected static string $view = 'filament.officer.pages.dashboard';
```

---

### 6. Global Panel Theme (optional but recommended)

Publish Filament's panel CSS variables so Phase 2/3 can customise colours to match
the dark charcoal background seen in the screenshot:

```bash
php artisan filament:assets
```

To override the sidebar background add to `resources/css/filament/officer/theme.css`:

```css
:root {
    --sidebar-bg: theme(colors.gray.900);
    --body-bg:    theme(colors.gray.950);
}
```

Register the custom theme in the panel provider:

```php
->viteTheme('resources/css/filament/officer/theme.css')
```

---

## Acceptance Criteria

- [ ] `php artisan serve` → `/officer` loads without errors.
- [ ] Page renders with a dark background.
- [ ] Page shows an `<h2>Sidebar</h2>` block on the left and `<h2>Main</h2>` on the right
  on `md:` and larger screens.
- [ ] On mobile the blocks stack vertically.
- [ ] `php artisan test` passes (no new failing tests).
- [ ] `php artisan pint --test` passes (no style violations).

---

## Files Created / Modified

| Action   | Path |
|----------|------|
| Create   | `app/Providers/Filament/OfficerPanelProvider.php` |
| Create   | `app/Filament/Officer/Pages/Dashboard.php` |
| Create   | `resources/views/filament/officer/pages/dashboard.blade.php` |
| Create   | `resources/css/filament/officer/theme.css` *(optional)* |
| Modify   | `bootstrap/providers.php` |

---

## What Is Explicitly Deferred

- Auth middleware and role guards → Phase 2
- Real sidebar navigation links → Phase 2
- Stat cards, priority queue table, team workload widgets → Phase 3
- Mock data import → Phase 2 / 3
