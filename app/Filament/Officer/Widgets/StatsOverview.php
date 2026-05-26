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

            Stat::make('Avg. Decision Time', ($data['avg_decision_days'] ?? 2.4).'d')
                ->description('0.3d faster')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->icon('heroicon-o-bolt')
                ->color('info'),
        ];
    }
}
