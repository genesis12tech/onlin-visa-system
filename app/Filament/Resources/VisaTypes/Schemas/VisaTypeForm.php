<?php

namespace App\Filament\Resources\VisaTypes\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class VisaTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('country_id')
                    ->relationship('country', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true)
                    ->helperText('e.g. TOURIST_30'),
                TextInput::make('processing_days')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->suffix('days'),
                TextInput::make('validity_days')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->suffix('days'),
                Select::make('max_entries')
                    ->options([
                        'single' => 'Single Entry',
                        'multiple' => 'Multiple Entry',
                        'unlimited' => 'Unlimited',
                    ])
                    ->required()
                    ->default('single'),
                Textarea::make('description')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
