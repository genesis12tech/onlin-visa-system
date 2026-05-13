<?php

namespace App\Filament\Widgets;

use App\Support\MockDataService;
use Filament\Widgets\ChartWidget;

class ApplicationsOverTimeWidget extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Applications over time';

    protected ?string $description = 'Monthly submissions — 2024';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $rows = MockDataService::overTime();

        return [
            'labels' => array_column($rows, 'month'),
            'datasets' => [
                [
                    'label' => 'Applications',
                    'data' => array_column($rows, 'count'),
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
            '6m' => '6M',
            '1y' => '1Y',
        ];
    }
}
