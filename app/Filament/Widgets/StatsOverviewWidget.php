<?php

namespace App\Filament\Widgets;

use App\Domain\Reporting\Models\DailyApplicationMetrics;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class StatsOverviewWidget extends BaseStatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $stats = Cache::remember('dashboard.stats', 300, function () {
            $thisMonth = Carbon::now()->startOfMonth();
            $lastMonth = Carbon::now()->subMonth()->startOfMonth();
            $lastMonthEnd = $thisMonth;

            $submittedTotal = DailyApplicationMetrics::sum('submitted_count');

            $submittedThisMonth = DailyApplicationMetrics::where('date', '>=', $thisMonth)
                ->sum('submitted_count');
            $submittedLastMonth = DailyApplicationMetrics::whereBetween('date', [$lastMonth, $lastMonthEnd])
                ->sum('submitted_count');

            $pendingToday = DailyApplicationMetrics::whereDate('date', today())
                ->sum('pending_count');

            $approvedThisMonth = DailyApplicationMetrics::where('date', '>=', $thisMonth)
                ->sum('approved_count');
            $approvedLastMonth = DailyApplicationMetrics::whereBetween('date', [$lastMonth, $lastMonthEnd])
                ->sum('approved_count');

            $rejectedThisMonth = DailyApplicationMetrics::where('date', '>=', $thisMonth)
                ->sum('rejected_count');

            $decidedThisMonth = $approvedThisMonth + $rejectedThisMonth;
            $rejectionRate = $decidedThisMonth > 0
                ? round(($rejectedThisMonth / $decidedThisMonth) * 100, 1).'%'
                : '0%';

            $submittedDiff = $submittedThisMonth - $submittedLastMonth;
            $approvedDiff = $approvedThisMonth - $approvedLastMonth;

            return [
                'total' => $submittedTotal,
                'total_trend' => ($submittedDiff >= 0 ? '+' : '').number_format($submittedDiff).' vs last month',
                'pending' => $pendingToday,
                'approved_month' => $approvedThisMonth,
                'approved_trend' => ($approvedDiff >= 0 ? '+' : '').number_format($approvedDiff).' vs last month',
                'rejection_rate' => $rejectionRate,
            ];
        });

        return [
            Stat::make('Total Applications', number_format($stats['total']))
                ->description($stats['total_trend'])
                ->descriptionIcon($this->trendIcon($stats['total_trend']))
                ->color('info'),

            Stat::make('Pending Review', number_format($stats['pending']))
                ->description('In queue as of today')
                ->color('warning'),

            Stat::make('Approved This Month', number_format($stats['approved_month']))
                ->description($stats['approved_trend'])
                ->descriptionIcon($this->trendIcon($stats['approved_trend']))
                ->color('success'),

            Stat::make('Rejection Rate', $stats['rejection_rate'])
                ->description('Of decided applications this month')
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
