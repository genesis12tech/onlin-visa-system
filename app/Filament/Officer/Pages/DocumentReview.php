<?php

namespace App\Filament\Officer\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class DocumentReview extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    protected static string|UnitEnum|null $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Document Review';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.officer.pages.document-review';

    public static function getNavigationBadge(): ?string
    {
        return '5';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
