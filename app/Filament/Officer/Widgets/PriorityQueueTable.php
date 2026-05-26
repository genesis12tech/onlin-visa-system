<?php

namespace App\Filament\Officer\Widgets;

use App\Domain\Applications\Models\VisaApplication;
use App\Support\MockOfficerData;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PriorityQueueTable extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;

    protected static ?string $heading = 'Priority Queue';

    protected static ?string $pollingInterval = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                VisaApplication::query()->whereNull('id')
            )
            ->records(fn () => collect(MockOfficerData::get('priority_queue', [])))
            ->columns([
                Tables\Columns\IconColumn::make('priority_flag')
                    ->label('Priority')
                    ->icon(fn ($record) => 'heroicon-s-circle')
                    ->color(fn ($record) => match ($record['priority'] ?? 'low') {
                        'high' => 'danger',
                        'medium' => 'warning',
                        default => 'success',
                    }),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Reference')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('applicant')
                    ->label('Applicant'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type'),

                Tables\Columns\TextColumn::make('days_pending')
                    ->label('Days')
                    ->badge()
                    ->color(fn ($state) => $state >= 7 ? 'danger' : 'success')
                    ->formatStateUsing(fn ($state) => $state.'d'),

                Tables\Columns\TextColumn::make('action')
                    ->label('')
                    ->default('Review')
                    ->badge()
                    ->color('gray'),
            ])
            ->paginated(false);
    }
}
