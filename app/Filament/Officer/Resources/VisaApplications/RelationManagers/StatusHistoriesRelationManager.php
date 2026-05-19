<?php

namespace App\Filament\Officer\Resources\VisaApplications\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatusHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistories';

    protected static ?string $title = 'Status History';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('to_status')
            ->defaultSort('created_at', 'asc')
            ->columns([
                TextColumn::make('from_status')
                    ->label('From')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? ucwords(str_replace('_', ' ', $state)) : '—'),

                TextColumn::make('to_status')
                    ->label('To')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (?string $state): string => $state ? ucwords(str_replace('_', ' ', $state)) : '—'),

                TextColumn::make('actor.name')
                    ->label('Actor')
                    ->placeholder('System'),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(80)
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
