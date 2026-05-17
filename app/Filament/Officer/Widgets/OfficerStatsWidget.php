<?php

namespace App\Filament\Officer\Widgets;

use App\Domain\Reporting\Models\OfficerPerformanceMetrics;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class OfficerStatsWidget extends BaseStatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $metrics = OfficerPerformanceMetrics::where('officer_id', auth()->id())
            ->where('date', '>=', Carbon::now()->startOfMonth())
            ->get();

        $reviewed = $metrics->sum('reviewed_count');
        $approved = $metrics->sum('approved_count');
        $rejected = $metrics->sum('rejected_count');
        $avgHours = $metrics->avg('avg_review_hours');

        return [
            Stat::make('Reviewed This Month', $reviewed)
                ->color('info'),

            Stat::make('Approved', $approved)
                ->description('of '.$reviewed.' reviewed')
                ->color('success'),

            Stat::make('Rejected', $rejected)
                ->color('danger'),

            Stat::make('Avg Review Time', $avgHours !== null ? round($avgHours, 1).'h' : '—')
                ->description('Per application this month')
                ->color('gray'),
        ];
    }
}
