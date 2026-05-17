<?php

namespace App\Filament\Widgets;

use App\Domain\Reporting\Models\DailyPaymentMetrics;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class PaymentStatsWidget extends BaseStatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $stats = Cache::remember('dashboard.payment_stats', 300, function () {
            $thisMonth = Carbon::now()->startOfMonth();

            $collectedToday = DailyPaymentMetrics::whereDate('date', today())
                ->sum('total_collected');

            $collectedThisMonth = DailyPaymentMetrics::where('date', '>=', $thisMonth)
                ->sum('total_collected');

            $succeededThisMonth = DailyPaymentMetrics::where('date', '>=', $thisMonth)
                ->sum('succeeded_count');

            $failedThisMonth = DailyPaymentMetrics::where('date', '>=', $thisMonth)
                ->sum('failed_count');

            $totalCount = $succeededThisMonth + $failedThisMonth;
            $successRate = $totalCount > 0
                ? round(($succeededThisMonth / $totalCount) * 100).'%'
                : '—';

            return [
                'today' => '$'.number_format($collectedToday / 100, 2),
                'month' => '$'.number_format($collectedThisMonth / 100, 2),
                'success_rate' => $successRate,
                'succeeded' => $succeededThisMonth,
                'failed' => $failedThisMonth,
            ];
        });

        return [
            Stat::make('Collected Today', $stats['today'])
                ->color('success'),

            Stat::make('Collected This Month', $stats['month'])
                ->color('success'),

            Stat::make('Payment Success Rate', $stats['success_rate'])
                ->description($stats['succeeded'].' succeeded · '.$stats['failed'].' failed this month')
                ->color('info'),
        ];
    }
}
