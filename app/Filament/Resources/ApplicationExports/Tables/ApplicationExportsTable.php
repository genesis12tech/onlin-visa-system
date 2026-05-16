<?php

namespace App\Filament\Resources\ApplicationExports\Tables;

use App\Domain\Reporting\Enums\ExportStatus;
use App\Domain\Reporting\Models\ApplicationExport;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ApplicationExportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ulid')
                    ->label('ID')
                    ->limit(12)
                    ->copyable(),

                TextColumn::make('requester.name')
                    ->label('Requested by'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ExportStatus $state): string => $state->label())
                    ->color(fn (ExportStatus $state): string => $state->color()),

                TextColumn::make('row_count')
                    ->label('Rows')
                    ->numeric(),

                TextColumn::make('generated_at')
                    ->dateTime('M j, Y H:i')
                    ->label('Generated'),

                TextColumn::make('created_at')
                    ->dateTime('M j, Y H:i')
                    ->label('Requested'),
            ])
            ->recordAction(null)
            ->actions([
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (ApplicationExport $record) => route('exports.download', $record->ulid))
                    ->openUrlInNewTab()
                    ->visible(fn (ApplicationExport $record) => $record->status === ExportStatus::Ready)
                    ->authorize(fn (ApplicationExport $record) => auth()->user()->can('download', $record)),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
