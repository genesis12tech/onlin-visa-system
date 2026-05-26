<?php

namespace App\Filament\Officer\Pages;

use App\Domain\Applications\Queries\PendingQueueCount;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ReviewQueue extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|UnitEnum|null $navigationGroup = 'Workspace';

    protected static ?string $navigationLabel = 'Review Queue';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.officer.pages.review-queue';

    public static function getNavigationBadge(): ?string
    {
        return (string) app(PendingQueueCount::class)->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
