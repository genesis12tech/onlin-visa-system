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
