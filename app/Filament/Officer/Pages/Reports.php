<?php

namespace App\Filament\Officer\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Reports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Reporting';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.officer.pages.reports';
}
