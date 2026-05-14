<?php

namespace App\Filament\Widgets;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
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
            $total = VisaApplication::count();

            $pending = VisaApplication::whereIn('status', [
                ApplicationStatus::Submitted->value,
                ApplicationStatus::UnderReview->value,
                ApplicationStatus::DocsRequired->value,
            ])->count();

            $thisMonth = Carbon::now()->startOfMonth();
            $lastMonth = Carbon::now()->subMonth()->startOfMonth();

            $approvedThisMonth = VisaApplication::where('status', ApplicationStatus::Approved->value)
                ->where('decision_at', '>=', $thisMonth)
                ->count();

            $approvedLastMonth = VisaApplication::where('status', ApplicationStatus::Approved->value)
                ->whereBetween('decision_at', [$lastMonth, $thisMonth])
                ->count();

            $submittedThisMonth = VisaApplication::where('submitted_at', '>=', $thisMonth)->count();
            $submittedLastMonth = VisaApplication::whereBetween('submitted_at', [$lastMonth, $thisMonth])->count();

            $rejectedThisMonth = VisaApplication::where('status', ApplicationStatus::Rejected->value)
                ->where('decision_at', '>=', $thisMonth)
                ->count();
            $decidedThisMonth = $approvedThisMonth + $rejectedThisMonth;
            $rejectionRate = $decidedThisMonth > 0
                ? round(($rejectedThisMonth / $decidedThisMonth) * 100, 1).'%'
                : '0%';

            $totalDiff = $submittedThisMonth - $submittedLastMonth;
            $approvedDiff = $approvedThisMonth - $approvedLastMonth;

            return [
                'total' => $total,
                'total_trend' => ($totalDiff >= 0 ? '+' : '').number_format($totalDiff).' vs last month',
                'pending' => $pending,
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
                ->description('Submitted, in review, or awaiting docs')
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
