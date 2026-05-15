<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Domain\Payments\Enums\PaymentStatus;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('visaApplication.tracking_number')
                    ->label('Application')
                    ->searchable()
                    ->sortable(),
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
                TextColumn::make('succeeded_at')
                    ->label('Paid At')
                    ->dateTime()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(PaymentStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordAction('view');
    }
}
