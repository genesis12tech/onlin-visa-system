<?php

namespace App\Filament\Officer\Widgets;

use App\Domain\Applications\Models\VisaApplication;
use Filament\Widgets\Widget;

class SlaAlertWidget extends Widget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.officer.widgets.sla-alert-widget';

    public function getBreachCount(): int
    {
        return VisaApplication::slaBreached()->count();
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['senior_officer', 'admin', 'super_admin']) ?? false;
    }
}
