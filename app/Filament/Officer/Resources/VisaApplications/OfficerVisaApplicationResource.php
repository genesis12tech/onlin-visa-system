<?php

namespace App\Filament\Officer\Resources\VisaApplications;

use App\Domain\Applications\Models\VisaApplication;
use App\Filament\Officer\Resources\VisaApplications\Pages\ListOfficerApplications;
use App\Filament\Officer\Resources\VisaApplications\Pages\ViewOfficerApplication;
use App\Filament\Officer\Resources\VisaApplications\RelationManagers\OfficerDocumentsRelationManager;
use App\Filament\Officer\Resources\VisaApplications\RelationManagers\OfficerNotesRelationManager;
use App\Filament\Officer\Resources\VisaApplications\Tables\OfficerApplicationsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OfficerVisaApplicationResource extends Resource
{
    protected static ?string $model = VisaApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'My Applications';

    protected static ?string $slug = 'applications';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['applicantProfile.nationality', 'visaType', 'officer']);

        if (auth()->user()?->hasRole('case_officer')) {
            $query->where('assigned_officer_id', auth()->id());
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return OfficerApplicationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            OfficerDocumentsRelationManager::class,
            OfficerNotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOfficerApplications::route('/'),
            'view' => ViewOfficerApplication::route('/{record}'),
        ];
    }
}
