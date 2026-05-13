<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class ApplicationsOverTimeWidget extends ChartWidget
{
    protected ?string $heading = 'Applications Over Time Widget';

    protected function getData(): array
    {
        return [
            //
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
