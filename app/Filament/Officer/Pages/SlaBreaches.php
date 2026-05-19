<?php

namespace App\Filament\Officer\Pages;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

class SlaBreaches extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationCircle;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?string $navigationLabel = 'SLA Breaches';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.officer.pages.sla-breaches';

    public function mount(): void
    {
        abort_unless(
            auth()->user()?->hasAnyRole(['senior_officer', 'admin', 'super_admin']),
            403,
        );
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['senior_officer', 'admin', 'super_admin']) ?? false;
    }

    public function getBreachCount(): int
    {
        return VisaApplication::slaBreached()->count();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                VisaApplication::slaBreached()
                    ->with(['applicantProfile', 'visaType', 'officer'])
            )
            ->columns([
                TextColumn::make('tracking_number')
                    ->label('Reference')
                    ->color('danger')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('applicant_name')
                    ->label('Applicant')
                    ->state(fn (VisaApplication $record): string => $record->applicantProfile?->full_name ?? '—'),

                TextColumn::make('visaType.name')
                    ->label('Visa Type'),

                TextColumn::make('officer.name')
                    ->label('Assigned To')
                    ->placeholder('Unassigned'),

                TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->date('M j, Y')
                    ->sortable(),

                TextColumn::make('sla_remaining_days')
                    ->label('Overdue By')
                    ->state(fn (VisaApplication $record): string => abs($record->sla_remaining_days).' days')
                    ->color('danger'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ApplicationStatus $state): string => $state->label())
                    ->color(fn (ApplicationStatus $state): string => $state->color()),
            ])
            ->defaultSort('submitted_at', 'asc')
            ->filters([])
            ->headerActions([])
            ->paginated([25, 50]);
    }
}
