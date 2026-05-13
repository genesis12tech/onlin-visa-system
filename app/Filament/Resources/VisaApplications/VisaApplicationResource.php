<?php

namespace App\Filament\Resources\VisaApplications;

use App\Filament\Resources\VisaApplications\Pages\CreateVisaApplication;
use App\Filament\Resources\VisaApplications\Pages\EditVisaApplication;
use App\Filament\Resources\VisaApplications\Pages\ListVisaApplications;
use App\Filament\Resources\VisaApplications\Schemas\VisaApplicationForm;
use App\Filament\Resources\VisaApplications\Tables\VisaApplicationsTable;
use App\Models\VisaApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VisaApplicationResource extends Resource
{
    protected static ?string $model = VisaApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVisaApplications::route('/'),
            'create' => CreateVisaApplication::route('/create'),
            'edit' => EditVisaApplication::route('/{record}/edit'),
        ];
    }
}
