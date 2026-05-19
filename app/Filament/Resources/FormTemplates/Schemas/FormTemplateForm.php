<?php

namespace App\Filament\Resources\FormTemplates\Schemas;

use App\Domain\Applications\Models\VisaType;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FormTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('visa_type_id')
                    ->label('Visa Type')
                    ->options(VisaType::where('is_active', true)->pluck('name', 'ulid'))
                    ->searchable()
                    ->required(),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('version')
                    ->numeric()
                    ->required()
                    ->default(1)
                    ->minValue(1),
                KeyValue::make('schema')
                    ->label('Form Schema (JSON)')
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->reorderable()
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->default(false)
                    ->helperText('Active templates are available for new applications.'),
            ]);
    }
}
