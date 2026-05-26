<?php

namespace App\Filament\Officer\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class MyAssigned extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Workspace';

    protected static ?string $navigationLabel = 'My Assigned';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.officer.pages.my-assigned';

    public static function getNavigationBadge(): ?string
    {
        return '8';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }
}
