<?php

namespace App\Filament\Officer\Pages;

use App\Filament\Officer\Widgets\DashboardHeader;
use App\Filament\Officer\Widgets\PriorityQueueTable;
use App\Filament\Officer\Widgets\StatsOverview;
use App\Filament\Officer\Widgets\TeamWorkload;
use App\Filament\Officer\Widgets\TodaysActivity;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Workspace';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = '';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.officer.pages.dashboard';

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 4,
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
