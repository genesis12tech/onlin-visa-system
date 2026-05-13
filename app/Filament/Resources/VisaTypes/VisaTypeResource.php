<?php

namespace App\Filament\Resources\VisaTypes;

use App\Domain\Applications\Models\VisaType;
use App\Filament\Resources\VisaTypes\Pages\CreateVisaType;
use App\Filament\Resources\VisaTypes\Pages\EditVisaType;
use App\Filament\Resources\VisaTypes\Pages\ListVisaTypes;
use App\Filament\Resources\VisaTypes\RelationManagers\FeesRelationManager;
use App\Filament\Resources\VisaTypes\Schemas\VisaTypeForm;
use App\Filament\Resources\VisaTypes\Tables\VisaTypesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class VisaTypeResource extends Resource
{
    protected static ?string $model = VisaType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'SETTINGS';

    protected static ?string $navigationLabel = 'Visa Types';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return VisaTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VisaTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            FeesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVisaTypes::route('/'),
            'create' => CreateVisaType::route('/create'),
            'edit' => EditVisaType::route('/{record}/edit'),
        ];
    }
}
