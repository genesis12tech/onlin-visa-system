<?php

namespace App\Filament\Officer\Resources\VisaApplications\Tables;

use App\Domain\Applications\Actions\AssignApplicationToOfficer;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

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

                TextColumn::make('sla_remaining_days')
                    ->label('SLA')
                    ->state(fn (VisaApplication $record): int => $record->sla_remaining_days)
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state < 0 => 'danger',
                        $state <= 2 => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (int $state): string => $state < 0 ? 'Breached' : "{$state}d left"),
            ])
            ->recordClasses(fn (VisaApplication $record): string => match (true) {
                $record->sla_remaining_days < 0 => 'border-l-4 border-red-500',
                $record->sla_remaining_days <= 2 => 'border-l-4 border-amber-500',
                $record->submitted_at?->isAfter(now()->subDay()) ?? false => 'border-l-4 border-purple-500',
                default => '',
            })
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(ApplicationStatus::cases())->mapWithKeys(
                        fn (ApplicationStatus $s) => [$s->value => $s->label()]
                    )->toArray()),

                SelectFilter::make('visa_type_id')
                    ->label('Visa Type')
                    ->relationship('visaType', 'name'),

                Filter::make('sla_at_risk')
                    ->label('SLA at risk')
                    ->query(fn (Builder $query) => $query->slaAtRisk()),

                Filter::make('sla_breached')
                    ->label('SLA breached')
                    ->query(fn (Builder $query) => $query->slaBreached()),

                Filter::make('resubmitted')
                    ->label('Resubmitted')
                    ->query(fn (Builder $query) => $query->where('status', ApplicationStatus::Resubmitted->value)),

                Filter::make('unassigned')
                    ->label('Unassigned')
                    ->query(fn (Builder $query) => $query->whereNull('assigned_officer_id'))
                    ->visible(fn (): bool => auth()->user()?->hasAnyRole(['senior_officer', 'admin', 'super_admin']) ?? false),

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
            ->recordActions([
                Action::make('assign')
                    ->label('Assign')
                    ->icon('heroicon-o-user-plus')
                    ->color('gray')
                    ->visible(fn (): bool => auth()->user()?->hasAnyRole(['senior_officer', 'admin', 'super_admin']) ?? false)
                    ->authorize(fn (VisaApplication $record): bool => auth()->user()?->can('reassign', $record) ?? false)
                    ->schema([
                        Select::make('officer_id')
                            ->label('Assign to officer')
                            ->options(User::role(['case_officer', 'senior_officer'])->pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(fn (VisaApplication $record, array $data) => (new AssignApplicationToOfficer)->execute(
                        $record,
                        User::findOrFail($data['officer_id']),
                        auth()->user(),
                    )),
            ])
            ->bulkActions([
                BulkAction::make('bulk_assign')
                    ->label('Assign to officer')
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn (): bool => auth()->user()?->hasAnyRole(['senior_officer', 'admin', 'super_admin']) ?? false)
                    ->schema([
                        Select::make('officer_id')
                            ->label('Assign to officer')
                            ->options(User::role(['case_officer', 'senior_officer'])->pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $officer = User::findOrFail($data['officer_id']);
                        $records->each(fn (VisaApplication $record) => (new AssignApplicationToOfficer)->execute(
                            $record,
                            $officer,
                            auth()->user(),
                        ));
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('submitted_at', 'asc')
            ->searchPlaceholder('Search reference or applicant…')
            ->paginated([10, 25, 50]);
    }
}
