<?php

namespace App\Filament\Resources\VisaApplications;

use App\Domain\Applications\Models\VisaApplication;
use App\Filament\Resources\VisaApplications\Pages\CreateVisaApplication;
use App\Filament\Resources\VisaApplications\Pages\EditVisaApplication;
use App\Filament\Resources\VisaApplications\Pages\ListVisaApplications;
use App\Filament\Resources\VisaApplications\Pages\ViewVisaApplication;
use App\Filament\Resources\VisaApplications\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\VisaApplications\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\VisaApplications\Schemas\VisaApplicationForm;
use App\Filament\Resources\VisaApplications\Tables\VisaApplicationsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class VisaApplicationResource extends Resource
{
    protected static ?string $model = VisaApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'MAIN';

    protected static ?string $navigationLabel = 'Applications';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        return (string) VisaApplication::count();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['applicantProfile.nationality', 'visaType', 'officer']);
    }

    public static function form(Schema $schema): Schema
    {
        return VisaApplicationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VisaApplicationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVisaApplications::route('/'),
            'view' => ViewVisaApplication::route('/{record}'),
            'create' => CreateVisaApplication::route('/create'),
            'edit' => EditVisaApplication::route('/{record}/edit'),
        ];
    }
}
