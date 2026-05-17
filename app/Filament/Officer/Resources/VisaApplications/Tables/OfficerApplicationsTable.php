<?php

namespace App\Filament\Officer\Resources\VisaApplications\Tables;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OfficerApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tracking_number')
                    ->label('Reference')
                    ->color('info')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('applicant_name')
                    ->label('Applicant')
                    ->state(fn (VisaApplication $record): string => $record->applicantProfile?->full_name ?? '—')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'applicantProfile',
                        fn (Builder $q) => $q->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]),
                    )),

                TextColumn::make('visaType.name')
                    ->label('Visa Type')
                    ->sortable(),

                TextColumn::make('nationality')
                    ->label('Nationality')
                    ->state(fn (VisaApplication $record): string => $record->applicantProfile?->nationality?->name ?? '—'),

                TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->date('M j, Y')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('days_in_queue')
                    ->label('Days Waiting')
                    ->state(fn (VisaApplication $record): string => $record->submitted_at
                        ? (string) $record->submitted_at->diffInDays(now())
                        : '—'
                    )
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('submitted_at', $direction === 'asc' ? 'desc' : 'asc')),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ApplicationStatus $state): string => $state->label())
                    ->color(fn (ApplicationStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(ApplicationStatus::cases())->mapWithKeys(
                        fn (ApplicationStatus $s) => [$s->value => $s->label()]
                    )->toArray()),

                SelectFilter::make('visa_type_id')
                    ->label('Visa Type')
                    ->relationship('visaType', 'name'),

                Filter::make('submitted_at')
                    ->label('Submitted Date')
                    ->schema([
                        DatePicker::make('submitted_from')->label('From'),
                        DatePicker::make('submitted_until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['submitted_from'], fn ($q) => $q->whereDate('submitted_at', '>=', $data['submitted_from']))
                            ->when($data['submitted_until'], fn ($q) => $q->whereDate('submitted_at', '<=', $data['submitted_until']));
                    }),
            ])
            ->defaultSort('submitted_at', 'asc')
            ->searchPlaceholder('Search reference or applicant…')
            ->paginated([10, 25, 50]);
    }
}
