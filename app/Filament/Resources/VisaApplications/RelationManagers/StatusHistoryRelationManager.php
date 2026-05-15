<?php

namespace App\Filament\Resources\VisaApplications\RelationManagers;

use App\Domain\Applications\Enums\ApplicationStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatusHistoryRelationManager extends RelationManager
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
            ->recordTitleAttribute('ulid')
            ->defaultSort('created_at', 'asc')
            ->columns([
                TextColumn::make('from_status')
                    ->label('From')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? (ApplicationStatus::tryFrom($state)?->label() ?? $state)
                        : '—'
                    )
                    ->color(fn (?string $state): string => $state
                        ? (ApplicationStatus::tryFrom($state)?->color() ?? 'gray')
                        : 'gray'
                    ),

                TextColumn::make('to_status')
                    ->label('To')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ApplicationStatus::tryFrom($state)?->label() ?? $state
                    )
                    ->color(fn (string $state): string => ApplicationStatus::tryFrom($state)?->color() ?? 'gray'
                    ),

                TextColumn::make('actor.name')
                    ->label('Actor')
                    ->placeholder('System'),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(80)
                    ->placeholder('—')
                    ->wrap(),

                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->headerActions([])
            ->toolbarActions([])
            ->recordActions([]);
    }
}
