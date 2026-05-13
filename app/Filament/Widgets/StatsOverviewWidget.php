<?php

namespace App\Filament\Widgets;

use App\Support\MockDataService;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseStatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $stats = MockDataService::stats();

        return [
            Stat::make('Total Applications', number_format($stats['total_applications']))
                ->description($stats['total_trend'])
                ->descriptionIcon($this->trendIcon($stats['total_trend']))
                ->color('info'),

            Stat::make('Pending Review', $stats['pending_review'])
                ->description($stats['pending_trend'])
                ->descriptionIcon($this->trendIcon($stats['pending_trend']))
                ->color('warning'),

            Stat::make('Approved This Month', number_format($stats['approved_this_month']))
                ->description($stats['approved_trend'])
                ->descriptionIcon($this->trendIcon($stats['approved_trend']))
                ->color('success'),

            Stat::make('Rejection Rate', $stats['rejection_rate'])
                ->description($stats['rejection_trend'])
                ->descriptionIcon($this->trendIcon($stats['rejection_trend']))
                ->color('success'),
        ];
    }

    private function trendIcon(string $trend): string
    {
        return str_starts_with($trend, '+')
            ? 'heroicon-m-arrow-trending-up'
            : 'heroicon-m-arrow-trending-down';
    }
}
