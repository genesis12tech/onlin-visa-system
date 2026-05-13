<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class ByVisaTypeWidget extends ChartWidget
{
    protected ?string $heading = 'By Visa Type Widget';

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
