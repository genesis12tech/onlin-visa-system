<?php

namespace App\Filament\Widgets;

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
        return [
            'labels' => ['Tourist', 'Student', 'Work', 'Business', 'Other'],
            'datasets' => [
                [
                    'label' => 'Applications',
                    'data' => [145, 72, 43, 29, 23],
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(99, 102, 241, 0.8)',
                        'rgba(148, 163, 184, 0.8)',
                    ],
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
}
