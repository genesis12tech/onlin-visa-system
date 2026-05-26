<?php

namespace App\Filament\Officer\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class SearchPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|UnitEnum|null $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Search';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.officer.pages.search';
}
