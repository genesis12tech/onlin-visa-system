<?php

namespace App\Filament\Widgets;

use App\Domain\Reporting\Models\DocumentRejectionMetrics;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class DocumentRejectionStatsWidget extends BaseStatsOverviewWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $stats = Cache::remember('dashboard.doc_rejection_stats', 300, function () {
            $thisMonth = Carbon::now()->startOfMonth();

            $totalRejections = DocumentRejectionMetrics::where('date', '>=', $thisMonth)
                ->sum('rejection_count');

            $topType = DocumentRejectionMetrics::with('documentType')
                ->where('date', '>=', $thisMonth)
                ->get()
                ->groupBy('document_type_id')
                ->map(fn ($group) => [
                    'name' => $group->first()->documentType?->name ?? 'Unknown',
                    'count' => $group->sum('rejection_count'),
                ])
                ->sortByDesc('count')
                ->first();

            return [
                'total' => $totalRejections,
                'top_type' => $topType ? $topType['name'].' ('.$topType['count'].')' : '—',
            ];
        });

        return [
            Stat::make('Document Rejections This Month', number_format($stats['total']))
                ->color($stats['total'] > 0 ? 'warning' : 'success'),

            Stat::make('Most Rejected Type', $stats['top_type'])
                ->color('warning'),
        ];
    }
}
