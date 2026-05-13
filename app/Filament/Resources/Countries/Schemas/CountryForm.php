<?php

namespace App\Filament\Resources\Countries\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CountryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('iso2')
                    ->label('ISO 2')
                    ->required()
                    ->maxLength(2),
                TextInput::make('iso3')
                    ->label('ISO 3')
                    ->required()
                    ->maxLength(3),
                TextInput::make('phone_code')
                    ->tel()
                    ->maxLength(10),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
