<?php

namespace App\Filament\Widgets;

use App\Domain\Reporting\Models\DailyApplicationMetrics;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class ApplicationsOverTimeWidget extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Applications over time';

    protected ?string $description = 'Monthly submissions';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $months = (int) ($this->filter ?? 6);

        $rows = DailyApplicationMetrics::perVisaType()
            ->where('date', '>=', Carbon::now()->subMonths($months - 1)->startOfMonth())
            ->orderBy('date')
            ->get()
            ->groupBy(fn ($row) => $row->date->format('M Y'))
            ->map(fn ($group) => $group->sum('submitted_count'));

        return [
            'labels' => $rows->keys()->toArray(),
            'datasets' => [
                [
                    'label' => 'Applications',
                    'data' => $rows->values()->toArray(),
                    'backgroundColor' => 'rgba(99, 102, 241, 0.8)',
                    'borderColor' => 'rgba(99, 102, 241, 1)',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getFilters(): ?array
    {
        return [
            '6' => '6M',
            '12' => '1Y',
        ];
    }
}
