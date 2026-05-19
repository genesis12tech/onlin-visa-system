<?php

namespace App\Filament\Officer\Pages;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TeamQueue extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?string $navigationLabel = 'Team Queue';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.officer.pages.team-queue';

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

    public function table(Table $table): Table
    {
        return $table
            ->query(
                VisaApplication::query()
                    ->with(['applicantProfile.nationality', 'visaType', 'officer'])
            )
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

                TextColumn::make('officer.name')
                    ->label('Assigned To')
                    ->placeholder('Unassigned'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ApplicationStatus $state): string => $state->label())
                    ->color(fn (ApplicationStatus $state): string => $state->color()),

                TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->date('M j, Y')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(ApplicationStatus::cases())->mapWithKeys(
                        fn (ApplicationStatus $s) => [$s->value => $s->label()]
                    )->toArray()),

                SelectFilter::make('assigned_officer_id')
                    ->label('Officer')
                    ->options(User::role(['case_officer', 'senior_officer'])->pluck('name', 'id')),

                Filter::make('unassigned')
                    ->label('Unassigned only')
                    ->query(fn (Builder $query) => $query->whereNull('assigned_officer_id')),

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
            ->paginated([25, 50, 100]);
    }
}
