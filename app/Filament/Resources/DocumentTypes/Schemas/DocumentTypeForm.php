<?php

namespace App\Filament\Resources\DocumentTypes\Schemas;

use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DocumentTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->rows(2)
                    ->nullable(),
                TagsInput::make('accepted_mime_types')
                    ->label('Accepted MIME Types')
                    ->placeholder('application/pdf')
                    ->helperText('e.g. application/pdf, image/jpeg, image/png')
                    ->required(),
                TextInput::make('max_size_kb')
                    ->label('Max File Size (KB)')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->default(5120)
                    ->helperText('5120 = 5MB'),
                TextInput::make('max_pages')
                    ->label('Max Pages')
                    ->numeric()
                    ->nullable()
                    ->minValue(1)
                    ->helperText('Leave blank for no page limit'),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
