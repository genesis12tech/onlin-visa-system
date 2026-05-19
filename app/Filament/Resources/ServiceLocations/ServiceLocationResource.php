<?php

namespace App\Filament\Resources\ServiceLocations;

use App\Domain\Applications\Models\ServiceLocation;
use App\Filament\Resources\ServiceLocations\Pages\CreateServiceLocation;
use App\Filament\Resources\ServiceLocations\Pages\EditServiceLocation;
use App\Filament\Resources\ServiceLocations\Pages\ListServiceLocations;
use App\Filament\Resources\ServiceLocations\Schemas\ServiceLocationForm;
use App\Filament\Resources\ServiceLocations\Tables\ServiceLocationsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ServiceLocationResource extends Resource
{
    protected static ?string $model = ServiceLocation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static string|UnitEnum|null $navigationGroup = 'SETTINGS';

    protected static ?string $navigationLabel = 'Service Locations';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ServiceLocationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServiceLocationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceLocations::route('/'),
            'create' => CreateServiceLocation::route('/create'),
            'edit' => EditServiceLocation::route('/{record}/edit'),
        ];
    }
}
