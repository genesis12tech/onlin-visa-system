<?php

namespace App\Filament\Resources\ServiceLocations\Schemas;

use App\Domain\Identity\Models\Country;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ServiceLocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Textarea::make('address')
                    ->required()
                    ->rows(3),
                TextInput::make('city')
                    ->required()
                    ->maxLength(255),
                Select::make('country_id')
                    ->label('Country')
                    ->options(Country::where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
