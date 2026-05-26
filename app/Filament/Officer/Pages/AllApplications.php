<?php

namespace App\Filament\Officer\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class AllApplications extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|UnitEnum|null $navigationGroup = 'Workspace';

    protected static ?string $navigationLabel = 'All Applications';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.officer.pages.all-applications';
}
