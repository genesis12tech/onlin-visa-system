<?php

namespace App\Filament\Officer\Widgets;

use App\Support\MockOfficerData;
use Filament\Widgets\Widget;

class TodaysActivity extends Widget
{
    protected string $view = 'filament.officer.widgets.todays-activity';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 5;

    public array $activities = [];

    public function mount(): void
    {
        $this->activities = MockOfficerData::get('todays_activity', []);
    }
}
