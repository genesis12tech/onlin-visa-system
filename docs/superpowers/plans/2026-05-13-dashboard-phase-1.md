
# Dashboard Phase 1 — Static UI Shell Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reproduce the VisaAdmin dashboard and applications list as a screenshot-faithful static UI inside Filament 4, using hard-coded PHP arrays — zero database queries.

**Architecture:** Custom Filament Dashboard page registers four widgets (stats, bar chart, donut chart, recent-apps table). The existing `VisaApplicationResource` list page is replaced with a static-data version. `AdminPanelProvider` is updated with dark mode, indigo primary color, brand name, and explicit navigation groups matching the sidebar in the screenshot.

**Tech Stack:** Laravel 12 · Filament 4 · Livewire 3 · PHP 8.4 · Tailwind CSS 4

---

## File Map

| Action | File | Responsibility |
|---|---|---|
| Modify | `app/Providers/Filament/AdminPanelProvider.php` | Dark mode, indigo color, brand, navigation groups |
| Create | `app/Filament/Pages/Dashboard.php` | Custom dashboard page that owns widget layout |
| Modify | `app/Filament/Widgets/StatsOverviewWidget.php` | Four hard-coded stat cards |
| Modify | `app/Filament/Widgets/ApplicationsOverTimeWidget.php` | Bar chart, hard-coded Jul–Dec 2024 data |
| Modify | `app/Filament/Widgets/ByVisaTypeWidget.php` | Doughnut chart, hard-coded visa-type segments |
| Modify | `app/Filament/Widgets/RecentApplicationsWidget.php` | Table widget with 5 hard-coded rows |
| Modify | `app/Filament/Resources/VisaApplications/Tables/VisaApplicationsTable.php` | 10 hard-coded rows, all columns, status badges |
| Modify | `app/Filament/Resources/VisaApplications/Pages/ListVisaApplications.php` | Override `getTableRecords()` to return static collection |
| Modify | `app/Filament/Resources/VisaApplications/VisaApplicationResource.php` | Navigation group/label/icon/badge |

---

## Task 1: Configure AdminPanelProvider — dark mode, color, brand, navigation

**Files:**
- Modify: `app/Providers/Filament/AdminPanelProvider.php`

- [ ] **Step 1: Replace the AdminPanelProvider panel configuration**

Replace the full `panel()` method body with:

```php
<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\VisaApplications\VisaApplicationResource;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile()
            ->passwordReset()
            ->darkMode(isForced: true)
            ->brandName('VisaAdmin')
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->navigationGroups([
                NavigationGroup::make('MAIN')->collapsible(false),
                NavigationGroup::make('SETTINGS')->collapsible(false),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
```

- [ ] **Step 2: Run Pint to format**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Verify no syntax errors**

```bash
php artisan config:clear && php artisan route:list --path=admin --compact
```

Expected: routes listed without errors.

- [ ] **Step 4: Commit**

```bash
git add app/Providers/Filament/AdminPanelProvider.php
git commit -m "feat: configure admin panel with dark mode, indigo color, and nav groups"
```

---

## Task 2: Create custom Dashboard page

**Files:**
- Create: `app/Filament/Pages/Dashboard.php`

The spec requires a custom Dashboard page that registers all four widgets. Filament's auto-discovered `\Filament\Pages\Dashboard` is registered via `AdminPanelProvider::pages()` — we replace it with our own class in `app/Filament/Pages/`.

- [ ] **Step 1: Create the Dashboard page file**

```php
<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ApplicationsOverTimeWidget;
use App\Filament\Widgets\ByVisaTypeWidget;
use App\Filament\Widgets\RecentApplicationsWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string $routePath = '/';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $navigationGroup = 'MAIN';

    protected static ?int $navigationSort = 1;

    public function getWidgets(): array
    {
        return [
            StatsOverviewWidget::class,
            ApplicationsOverTimeWidget::class,
            ByVisaTypeWidget::class,
            RecentApplicationsWidget::class,
        ];
    }

    public function getColumns(): int | array
    {
        return 2;
    }
}
```

- [ ] **Step 2: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Visit `/admin` to verify the page loads**

Open `https://visa-application.test/admin` in a browser. Expect the Dashboard page with the heading "Dashboard" and no widget errors (widgets are empty stubs at this point — that's fine).

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Pages/Dashboard.php
git commit -m "feat: add custom Dashboard page with widget layout"
```

---

## Task 3: Implement StatsOverviewWidget — four stat cards

**Files:**
- Modify: `app/Filament/Widgets/StatsOverviewWidget.php`

- [ ] **Step 1: Replace the widget with four hard-coded stats**

```php
<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseStatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        return [
            Stat::make('Total Applications', '1,284')
                ->description('↑ 12% vs last month')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('info'),

            Stat::make('Pending Review', '47')
                ->description('↑ 8 since yesterday')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Approved This Month', '312')
                ->description('↑ 18% vs last month')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Rejection Rate', '8.4%')
                ->description('↓ 2.1% improvement')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),
        ];
    }
}
```

- [ ] **Step 2: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Verify stats appear on `/admin`**

Refresh `https://visa-application.test/admin`. Expect four stat cards: Total Applications (1,284), Pending Review (47), Approved This Month (312), Rejection Rate (8.4%).

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Widgets/StatsOverviewWidget.php
git commit -m "feat: add hard-coded stat cards to StatsOverviewWidget"
```

---

## Task 4: Implement ApplicationsOverTimeWidget — bar chart

**Files:**
- Modify: `app/Filament/Widgets/ApplicationsOverTimeWidget.php`

- [ ] **Step 1: Replace the widget with bar chart and hard-coded data**

```php
<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class ApplicationsOverTimeWidget extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Applications over time';

    protected ?string $description = 'Monthly submissions — 2024';

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        return [
            'labels' => ['Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            'datasets' => [
                [
                    'label' => 'Applications',
                    'data' => [98, 145, 112, 189, 204, 312],
                    'backgroundColor' => 'rgba(99, 102, 241, 0.8)',
                    'borderColor' => 'rgba(99, 102, 241, 1)',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getFilters(): ?array
    {
        return [
            '6m' => '6M',
            '1y' => '1Y',
        ];
    }
}
```

- [ ] **Step 2: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Verify bar chart appears**

Refresh `/admin`. Expect a bar chart with six bars (Jul–Dec) with purple/indigo color and the filter buttons "6M" and "1Y" visible.

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Widgets/ApplicationsOverTimeWidget.php
git commit -m "feat: add hard-coded bar chart to ApplicationsOverTimeWidget"
```

---

## Task 5: Implement ByVisaTypeWidget — doughnut chart

**Files:**
- Modify: `app/Filament/Widgets/ByVisaTypeWidget.php`

- [ ] **Step 1: Replace with doughnut chart and hard-coded segments**

```php
<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class ByVisaTypeWidget extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'By visa type';

    protected ?string $description = 'Current month';

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        return [
            'labels' => ['Tourist', 'Student', 'Work', 'Business', 'Other'],
            'datasets' => [
                [
                    'label' => 'Applications',
                    'data' => [145, 72, 43, 29, 23],
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.8)',   // blue — Tourist
                        'rgba(16, 185, 129, 0.8)',   // green — Student
                        'rgba(245, 158, 11, 0.8)',   // amber — Work
                        'rgba(99, 102, 241, 0.8)',   // indigo — Business
                        'rgba(148, 163, 184, 0.8)',  // grey — Other
                    ],
                    'borderWidth' => 2,
                    'borderColor' => '#1E293B',
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'cutout' => '65%',
        ];
    }
}
```

- [ ] **Step 2: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Verify doughnut chart appears**

Refresh `/admin`. Expect a doughnut chart with five coloured segments (Tourist, Student, Work, Business, Other) and a legend below.

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Widgets/ByVisaTypeWidget.php
git commit -m "feat: add hard-coded doughnut chart to ByVisaTypeWidget"
```

---

## Task 6: Implement RecentApplicationsWidget — static table

**Files:**
- Modify: `app/Filament/Widgets/RecentApplicationsWidget.php`

The `TableWidget` requires a query or a static source. In Filament 4, `Table::records(?Closure $dataSource)` accepts a Closure that returns an array of arrays (not stdClass). When `->records()` is set, `hasQuery()` returns false and no DB query runs.

- [ ] **Step 1: Replace with static 5-row table**

```php
<?php

namespace App\Filament\Widgets;

use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;

class RecentApplicationsWidget extends BaseTableWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Recent applications';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => [
                ['reference' => 'VA-2024-A1F3K2', 'applicant' => 'Arjun Mehta',     'visa_type' => 'Tourist',  'submitted' => 'Jan 14, 2025', 'status' => 'Submitted'],
                ['reference' => 'VA-2024-B2G4L3', 'applicant' => 'Sofia Chen',      'visa_type' => 'Student',  'submitted' => 'Feb 1, 2025',  'status' => 'Under review'],
                ['reference' => 'VA-2024-C3H5M4', 'applicant' => 'James Okonkwo',   'visa_type' => 'Work',     'submitted' => 'Jan 20, 2025', 'status' => 'Approved'],
                ['reference' => 'VA-2024-D4I6N5', 'applicant' => 'Maria Santos',    'visa_type' => 'Tourist',  'submitted' => 'Mar 5, 2025',  'status' => 'Docs required'],
                ['reference' => 'VA-2024-E5J7O6', 'applicant' => 'Ahmed Al-Rashid', 'visa_type' => 'Business', 'submitted' => 'Jan 30, 2025', 'status' => 'Under review'],
            ])
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->color('info'),
                TextColumn::make('applicant')
                    ->label('Applicant'),
                TextColumn::make('visa_type')
                    ->label('Visa Type'),
                TextColumn::make('submitted')
                    ->label('Submitted'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Submitted'     => 'info',
                        'Under review'  => 'warning',
                        'Approved'      => 'success',
                        'Docs required' => 'primary',
                        'Rejected'      => 'danger',
                        default         => 'gray',
                    }),
            ])
            ->headerActions([
                Action::make('viewAll')
                    ->label('View all →')
                    ->url(fn () => route('filament.admin.resources.visa-applications.index'))
                    ->color('primary'),
            ])
            ->paginated(false);
    }
}
```

- [ ] **Step 2: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Verify recent applications table appears**

Refresh `/admin`. Expect a table headed "Recent applications" showing exactly 5 rows. Status badges should be coloured (blue, amber, green, purple, amber). A "View all →" button should appear in the header.

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Widgets/RecentApplicationsWidget.php
git commit -m "feat: add 5-row static recent applications table widget"
```

---

## Task 7: Update VisaApplicationResource navigation

**Files:**
- Modify: `app/Filament/Resources/VisaApplications/VisaApplicationResource.php`

- [ ] **Step 1: Update the resource with correct navigation group, label, and badge**

```php
<?php

namespace App\Filament\Resources\VisaApplications;

use App\Filament\Resources\VisaApplications\Pages\CreateVisaApplication;
use App\Filament\Resources\VisaApplications\Pages\EditVisaApplication;
use App\Filament\Resources\VisaApplications\Pages\ListVisaApplications;
use App\Filament\Resources\VisaApplications\Schemas\VisaApplicationForm;
use App\Filament\Resources\VisaApplications\Tables\VisaApplicationsTable;
use App\Models\VisaApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class VisaApplicationResource extends Resource
{
    protected static ?string $model = VisaApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'MAIN';

    protected static ?string $navigationLabel = 'Applications';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        return '12';
    }

    public static function form(Schema $schema): Schema
    {
        return VisaApplicationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VisaApplicationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVisaApplications::route('/'),
            'create' => CreateVisaApplication::route('/create'),
            'edit' => EditVisaApplication::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 2: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Verify sidebar badge**

Refresh `/admin`. Expect "Applications" in the sidebar under "MAIN" with a badge showing "12".

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Resources/VisaApplications/VisaApplicationResource.php
git commit -m "feat: add MAIN navigation group, badge 12, and correct label to VisaApplicationResource"
```

---

## Task 8: Implement VisaApplicationsTable — 10 hard-coded rows

**Files:**
- Modify: `app/Filament/Resources/VisaApplications/Tables/VisaApplicationsTable.php`
- Modify: `app/Filament/Resources/VisaApplications/Pages/ListVisaApplications.php`

In Filament 4, `Table::records(?Closure $dataSource)` accepts a Closure returning arrays. When `->records()` is set, `Table::hasQuery()` returns false, bypassing the Eloquent query entirely — no DB calls. The Closure return values must be **PHP arrays** (not stdClass or Model). Each item is keyed by its collection index automatically via `ArrayRecord`.

- [ ] **Step 1: Replace VisaApplicationsTable with full column set, static rows, and no bulk actions**

```php
<?php

namespace App\Filament\Resources\VisaApplications\Tables;

use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VisaApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->records(fn () => [
                ['reference' => 'VA-2024-A1F3K2', 'applicant' => 'Arjun Mehta',     'visa_type' => 'Tourist',  'nationality' => 'Indian',     'travel_date' => 'Jan 14, 2025', 'status' => 'Submitted',     'assigned_to' => 'Unassigned'],
                ['reference' => 'VA-2024-B2G4L3', 'applicant' => 'Sofia Chen',      'visa_type' => 'Student',  'nationality' => 'Chinese',    'travel_date' => 'Feb 1, 2025',  'status' => 'Under review',  'assigned_to' => 'Priya Mehta'],
                ['reference' => 'VA-2024-C3H5M4', 'applicant' => 'James Okonkwo',   'visa_type' => 'Work',     'nationality' => 'Nigerian',   'travel_date' => 'Jan 20, 2025', 'status' => 'Approved',      'assigned_to' => 'Rahul Sharma'],
                ['reference' => 'VA-2024-D4I6N5', 'applicant' => 'Maria Santos',    'visa_type' => 'Tourist',  'nationality' => 'Brazilian',  'travel_date' => 'Mar 5, 2025',  'status' => 'Docs required', 'assigned_to' => 'Anita Desai'],
                ['reference' => 'VA-2024-E5J7O6', 'applicant' => 'Ahmed Al-Rashid', 'visa_type' => 'Business', 'nationality' => 'Saudi',      'travel_date' => 'Jan 30, 2025', 'status' => 'Under review',  'assigned_to' => 'Mohammed Khan'],
                ['reference' => 'VA-2024-F6K8P7', 'applicant' => 'Priya Nair',      'visa_type' => 'Student',  'nationality' => 'Indian',     'travel_date' => 'Feb 15, 2025', 'status' => 'Submitted',     'assigned_to' => 'Unassigned'],
                ['reference' => 'VA-2024-G7L9Q8', 'applicant' => 'Lucas Müller',    'visa_type' => 'Tourist',  'nationality' => 'German',     'travel_date' => 'Dec 28, 2024', 'status' => 'Approved',      'assigned_to' => 'Priya Mehta'],
                ['reference' => 'VA-2024-H8M0R9', 'applicant' => 'Yuki Tanaka',     'visa_type' => 'Medical',  'nationality' => 'Japanese',   'travel_date' => 'Jan 10, 2025', 'status' => 'Rejected',      'assigned_to' => 'Rahul Sharma'],
                ['reference' => 'VA-2024-I9N1S0', 'applicant' => 'Emma Wilson',     'visa_type' => 'Work',     'nationality' => 'Australian', 'travel_date' => 'Feb 5, 2025',  'status' => 'Submitted',     'assigned_to' => 'Unassigned'],
                ['reference' => 'VA-2024-J0O2T1', 'applicant' => 'Ravi Krishnan',   'visa_type' => 'Business', 'nationality' => 'Indian',     'travel_date' => 'Jan 22, 2025', 'status' => 'Under review',  'assigned_to' => 'Anita Desai'],
            ])
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->color('info'),

                TextColumn::make('applicant')
                    ->label('Applicant'),

                TextColumn::make('visa_type')
                    ->label('Visa Type'),

                TextColumn::make('nationality')
                    ->label('Nationality'),

                TextColumn::make('travel_date')
                    ->label('Travel Date'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Submitted'     => 'info',
                        'Under review'  => 'warning',
                        'Approved'      => 'success',
                        'Docs required' => 'primary',
                        'Rejected'      => 'danger',
                        default         => 'gray',
                    }),

                TextColumn::make('assigned_to')
                    ->label('Assigned To'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'Submitted'     => 'Submitted',
                        'Under review'  => 'In review',
                        'Approved'      => 'Approved',
                        'Rejected'      => 'Rejected',
                    ]),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->url('#')
                    ->color('gray'),
                Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->action(fn () => null),
                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->action(fn () => null),
            ])
            ->toolbarActions([])
            ->searchPlaceholder('Search reference, name…')
            ->paginated([10, 25, 50]);
    }
}
```

- [ ] **Step 2: Remove CreateAction from ListVisaApplications header**

`->records()` in the table config is all we need — no page-level overrides required. Just remove the `CreateAction`:

```php
<?php

namespace App\Filament\Resources\VisaApplications\Pages;

use App\Filament\Resources\VisaApplications\VisaApplicationResource;
use Filament\Resources\Pages\ListRecords;

class ListVisaApplications extends ListRecords
{
    protected static string $resource = VisaApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
```

- [ ] **Step 3: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: Verify the applications list page**

Visit `https://visa-application.test/admin/visa-applications`. Expect 10 rows with all columns, status badges in the correct colours, and View/Approve/Reject actions per row.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/VisaApplications/Tables/VisaApplicationsTable.php \
        app/Filament/Resources/VisaApplications/Pages/ListVisaApplications.php
git commit -m "feat: add 10 hard-coded rows and all columns to applications list"
```

---

## Task 9: Add remaining MAIN and SETTINGS nav groups to other resources

**Files:**
- Modify: `app/Filament/Resources/Users/UserResource.php`
- Modify: `app/Filament/Resources/VisaTypes/VisaTypeResource.php`

The screenshot shows Applicants, Officers under MAIN and Visa Types, Reports, Settings under SETTINGS. The `VisaTypeResource` and `UserResource` need navigation groups so they appear in the correct sidebar sections. Applicants, Officers, Reports, and Settings resources don't exist yet — so we only update the existing ones.

- [ ] **Step 1: Update UserResource navigation group**

In `app/Filament/Resources/Users/UserResource.php`, change the `$navigationGroup` to `'MAIN'` and add a sort:

```php
protected static string|UnitEnum|null $navigationGroup = 'MAIN';

protected static ?string $navigationLabel = 'Officers';

protected static ?int $navigationSort = 4;
```

- [ ] **Step 2: Update VisaTypeResource navigation group**

In `app/Filament/Resources/VisaTypes/VisaTypeResource.php`, add:

```php
protected static string|UnitEnum|null $navigationGroup = 'SETTINGS';

protected static ?string $navigationLabel = 'Visa Types';

protected static ?int $navigationSort = 1;
```

Check the existing file first to see the current property declarations, then insert accordingly.

- [ ] **Step 3: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: Verify sidebar groups**

Refresh `/admin`. Expect the sidebar to show:
- MAIN: Dashboard, Applications (badge 12), Officers
- SETTINGS: Visa Types

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/Users/UserResource.php \
        app/Filament/Resources/VisaTypes/VisaTypeResource.php
git commit -m "feat: assign resources to MAIN/SETTINGS navigation groups"
```

---

## Task 10: Run tests and verify acceptance criteria

- [ ] **Step 1: Run the full test suite**

```bash
php artisan test --compact
```

Expected: all tests pass (green).

- [ ] **Step 2: Manual acceptance checklist**

Visit `https://visa-application.test/admin` and verify:

- [ ] Four stat cards: Total Applications 1,284 · Pending Review 47 · Approved This Month 312 · Rejection Rate 8.4%
- [ ] Bar chart with bars Jul–Dec 2024 (values 98, 145, 112, 189, 204, 312) and "6M/1Y" filter buttons
- [ ] Doughnut chart with five segments (Tourist 145, Student 72, Work 43, Business 29, Other 23)
- [ ] Recent applications table with 5 rows and correct status badge colours
- [ ] Sidebar: MAIN group contains Dashboard (active), Applications (badge 12), Officers; SETTINGS group contains Visa Types
- [ ] Dark background throughout — no white/light panels

Visit `https://visa-application.test/admin/visa-applications` and verify:

- [ ] 10 rows displayed with Reference, Applicant, Visa Type, Nationality, Travel Date, Status, Assigned To, Actions columns
- [ ] Status badges: Submitted=blue, Under review=amber, Approved=green, Docs required=purple, Rejected=red
- [ ] View/Approve/Reject action buttons per row
- [ ] Search bar placeholder "Search reference, name…"
- [ ] No database queries fire on either page

- [ ] **Step 3: Commit (if any final fixes)**

```bash
git add -p
git commit -m "fix: address final dashboard phase-1 acceptance issues"
```

---

## Notes for the implementer

**Static data without a model:** In Filament 4, `Table::records(?Closure $dataSource)` accepts a Closure returning an array of arrays or a Collection of arrays. When `->records()` is set, `Table::hasQuery()` returns false, causing `getTableRecords()` to use the Closure instead of any Eloquent query. Records must be **PHP arrays**, not stdClass objects — Filament's `ArrayRecord` helper keys them by their array index (`__key` field) automatically.

**Why `->records()` bypasses the model query:** `InteractsWithTable::makeTable()` sets up a `->query()` closure first; then `table()` is called where we override with `->records()`. The `hasQuery()` check reads `! $this->dataSource`, so setting `dataSource` wins even though `query` is also set. No Eloquent query ever executes.

**Column state for array records:** `TextColumn::make('reference')` resolves state via `data_get($record, 'reference')` which works for both arrays and objects.

**Navigation groups:** Groups must be declared in `AdminPanelProvider` (via `->navigationGroups([...])`) *and* referenced by the exact same string in each resource's `$navigationGroup`. Case-sensitive.

**`darkMode(isForced: true)`:** Filament 4 supports a named argument `isForced` that locks the panel to dark mode regardless of browser preference.
