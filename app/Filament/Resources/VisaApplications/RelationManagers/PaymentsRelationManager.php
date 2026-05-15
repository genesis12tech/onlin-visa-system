<?php

namespace App\Filament\Resources\VisaApplications\RelationManagers;

use App\Domain\Payments\Enums\PaymentStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ulid')
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (PaymentStatus $state): string => $state->color()),
                TextColumn::make('amount_total')
                    ->label('Amount')
                    ->formatStateUsing(fn (int $state, $record): string => number_format($state / 100, 2).' '.$record->currency),
                TextColumn::make('provider_checkout_session_id')
                    ->label('Session ID')
                    ->limit(20)
                    ->placeholder('—'),
                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->placeholder('—'),
                TextColumn::make('succeeded_at')
                    ->label('Paid At')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->headerActions([])
            ->toolbarActions([])
            ->recordActions([]);
    }
}
