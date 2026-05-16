<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('action')
                    ->label('Action')
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('user.name')
                    ->label('Actor')
                    ->default('—'),

                TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn (string $state): string => class_basename($state)),

                TextColumn::make('subject_id')
                    ->label('Subject ID')
                    ->limit(12)
                    ->copyable(),

                TextColumn::make('ip_address')
                    ->label('IP'),

                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('M j, Y H:i:s')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('action')
                    ->form([
                        TextInput::make('action')
                            ->placeholder('e.g. document.downloaded'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when(
                            $data['action'] ?? null,
                            fn ($q, $v) => $q->where('action', 'like', "%{$v}%")
                        )
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordAction(null)
            ->paginated([25, 50, 100]);
    }
}
