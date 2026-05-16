<?php

namespace App\Filament\Resources\ApplicationExports;

use App\Domain\Reporting\Models\ApplicationExport;
use App\Filament\Resources\ApplicationExports\Pages\ListApplicationExports;
use App\Filament\Resources\ApplicationExports\Tables\ApplicationExportsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ApplicationExportResource extends Resource
{
    protected static ?string $model = ApplicationExport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static string|UnitEnum|null $navigationGroup = 'SETTINGS';

    protected static ?string $navigationLabel = 'Exports';

    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return ApplicationExportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApplicationExports::route('/'),
        ];
    }
}
