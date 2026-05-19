<?php

namespace App\Filament\Officer\Widgets;

use App\Domain\Applications\Models\VisaApplication;
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
        $officerId = auth()->id();

        $assigned = VisaApplication::where('assigned_officer_id', $officerId)
            ->whereNull('decision_at')
            ->count();

        $slaAtRisk = VisaApplication::slaAtRisk()
            ->where('assigned_officer_id', $officerId)
            ->count();

        // TODO(M7): OfficerPerformanceMetrics is populated by GenerateDailyMetricsJob (nightly).
        // Stats show 0 until that job runs for the first time.
        $metrics = OfficerPerformanceMetrics::where('officer_id', $officerId)
            ->where('date', '>=', Carbon::now()->startOfMonth())
            ->get();

        $completed = $metrics->sum('reviewed_count');
        $avgHours = $metrics->avg('avg_review_hours');

        return [
            Stat::make('Assigned', $assigned)
                ->description('Applications in your queue')
                ->color('info'),

            Stat::make('Completed (MTD)', $completed)
                ->description('Reviews this month')
                ->color('success'),

            Stat::make('Avg. Turnaround', $avgHours !== null ? round($avgHours, 1).'h' : '—')
                ->description('Per application this month')
                ->color('gray'),

            Stat::make('SLA at Risk', $slaAtRisk)
                ->description('Due within 2 days')
                ->color($slaAtRisk > 0 ? 'warning' : 'success'),
        ];
    }
}
