<?php

namespace App\Filament\Widgets;

use App\Support\MockDataService;
use Filament\Widgets\ChartWidget;

class ByVisaTypeWidget extends ChartWidget
{
    protected string $view = 'filament.widgets.by-visa-type-widget';

    protected static ?int $sort = 3;

    protected ?string $heading = 'By visa type';

    protected ?string $description = 'Current month';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $rows = MockDataService::byVisaType();

        return [
            'labels' => array_column($rows, 'type'),
            'datasets' => [
                [
                    'label' => 'Applications',
                    'data' => array_column($rows, 'count'),
                    'backgroundColor' => array_map(
                        fn (array $row) => $this->hexToRgba($row['color'], 0.8),
                        $rows,
                    ),
                    'borderWidth' => 2,
                    'borderColor' => '#1E293B',
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'cutout' => '65%',
        ];
    }

    private function hexToRgba(string $hex, float $alpha = 1.0): string
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return "rgba($r, $g, $b, $alpha)";
    }
}
