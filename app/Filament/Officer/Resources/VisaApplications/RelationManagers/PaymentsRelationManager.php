<?php

namespace App\Filament\Officer\Resources\VisaApplications\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    public static function canViewForRecord(mixed $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->hasAnyRole(['finance_officer', 'senior_officer', 'admin', 'super_admin']) ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ulid')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state): string => number_format($state / 100, 2)),

                TextColumn::make('currency')
                    ->label('Currency')
                    ->formatStateUsing(fn ($state): string => strtoupper($state ?? 'USD')),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('provider')
                    ->label('Provider')
                    ->placeholder('—'),

                TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime()
                    ->placeholder('Pending'),
            ])
            ->filters([])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
