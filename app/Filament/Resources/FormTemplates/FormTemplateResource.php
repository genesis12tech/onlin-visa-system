<?php

namespace App\Filament\Resources\FormTemplates;

use App\Domain\Applications\Models\FormTemplate;
use App\Filament\Resources\FormTemplates\Pages\CreateFormTemplate;
use App\Filament\Resources\FormTemplates\Pages\EditFormTemplate;
use App\Filament\Resources\FormTemplates\Pages\ListFormTemplates;
use App\Filament\Resources\FormTemplates\Schemas\FormTemplateForm;
use App\Filament\Resources\FormTemplates\Tables\FormTemplatesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class FormTemplateResource extends Resource
{
    protected static ?string $model = FormTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = 'SETTINGS';

    protected static ?string $navigationLabel = 'Form Templates';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canEdit(Model $record): bool
    {
        return $record->published_at === null;
    }

    public static function canDelete(Model $record): bool
    {
        return $record->published_at === null;
    }

    public static function canDeleteAny(): bool
    {
        return true;
    }

    public static function form(Schema $schema): Schema
    {
        return FormTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FormTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFormTemplates::route('/'),
            'create' => CreateFormTemplate::route('/create'),
            'edit' => EditFormTemplate::route('/{record}/edit'),
        ];
    }
}
