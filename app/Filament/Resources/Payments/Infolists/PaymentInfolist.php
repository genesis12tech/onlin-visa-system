<?php

namespace App\Filament\Resources\Payments\Infolists;

use App\Domain\Payments\Enums\PaymentStatus;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payment Details')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (PaymentStatus $state): string => $state->color()),
                        TextEntry::make('amount_total')
                            ->label('Total Amount')
                            ->formatStateUsing(fn (int $state, $record): string => number_format($state / 100, 2).' '.$record->currency),
                        TextEntry::make('provider'),
                        TextEntry::make('provider_checkout_session_id')
                            ->label('Checkout Session')
                            ->placeholder('—'),
                        TextEntry::make('provider_payment_intent_id')
                            ->label('Payment Intent')
                            ->placeholder('—'),
                        TextEntry::make('failure_reason')
                            ->placeholder('—'),
                        TextEntry::make('succeeded_at')
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->dateTime(),
                    ]),
                ]),
            Section::make('Line Items')
                ->schema([
                    RepeatableEntry::make('items')
                        ->schema([
                            TextEntry::make('description'),
                            TextEntry::make('quantity'),
                            TextEntry::make('unit_amount')
                                ->formatStateUsing(fn (int $state, $record): string => number_format($state / 100, 2)),
                            TextEntry::make('total_amount')
                                ->formatStateUsing(fn (int $state, $record): string => number_format($state / 100, 2)),
                        ])
                        ->columns(4),
                ]),
            Section::make('Invoice')
                ->schema([
                    TextEntry::make('invoice.invoice_number')->placeholder('Not yet generated'),
                    TextEntry::make('invoice.issued_at')->dateTime()->placeholder('—'),
                    TextEntry::make('invoice.pdf_storage_path')->label('PDF Path')->placeholder('Pending generation'),
                ]),
        ]);
    }
}
