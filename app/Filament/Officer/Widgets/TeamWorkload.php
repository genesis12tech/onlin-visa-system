<?php

namespace App\Filament\Officer\Widgets;

use App\Support\MockOfficerData;
use Filament\Widgets\Widget;

class TeamWorkload extends Widget
{
    protected string $view = 'filament.officer.widgets.team-workload';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 4;

    public array $members = [];

    public function mount(): void
    {
        $this->members = MockOfficerData::get('team_workload', []);
    }
}
