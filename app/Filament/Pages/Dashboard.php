<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ApplicationsOverTimeWidget;
use App\Filament\Widgets\ByVisaTypeWidget;
use App\Filament\Widgets\RecentApplicationsWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use UnitEnum;

class Dashboard extends BaseDashboard
{
    protected static string $routePath = '/';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static string|UnitEnum|null $navigationGroup = 'MAIN';

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

    public function getColumns(): int|array
    {
        return 2;
    }
}
