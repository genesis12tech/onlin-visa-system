<?php

namespace App\Filament\Resources\DocumentTypes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('accepted_mime_types')
                    ->label('Accepted Types')
                    ->badge()
                    ->separator(','),
                TextColumn::make('max_size_kb')
                    ->label('Max Size')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1024, 1).'MB'),
                TextColumn::make('max_pages')
                    ->label('Max Pages')
                    ->placeholder('Unlimited'),
                IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
