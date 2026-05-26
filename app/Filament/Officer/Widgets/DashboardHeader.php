<?php

namespace App\Filament\Officer\Widgets;

use App\Support\MockOfficerData;
use Filament\Widgets\Widget;

class DashboardHeader extends Widget
{
    protected string $view = 'filament.officer.widgets.dashboard-header';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public string $officerName = '';

    public int $pendingCount = 0;

    public string $todayLabel = '';

    public function mount(): void
    {
        $data = MockOfficerData::load();

        $this->officerName = $data['officer']['name'] ?? auth()->user()?->name ?? 'Officer';
        $this->pendingCount = $data['pending_queue_count'] ?? 12;
        $this->todayLabel = now()->format('l, j F Y');
    }
}
