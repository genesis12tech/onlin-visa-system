<?php

namespace App\Filament\Officer\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class OfficerDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.officer.pages.officer-dashboard';

    public function getMockData(): array
    {
        return require database_path('mock/officer-data.php');
    }
}
