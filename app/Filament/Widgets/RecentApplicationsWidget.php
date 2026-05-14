<?php

namespace App\Filament\Widgets;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;

class RecentApplicationsWidget extends BaseTableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent applications';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                VisaApplication::latest('submitted_at')
                    ->with(['applicantProfile.nationality', 'visaType', 'officer'])
                    ->limit(5),
            )
            ->columns([
                TextColumn::make('tracking_number')
                    ->label('Reference')
                    ->color('info'),

                TextColumn::make('applicant_name')
                    ->label('Applicant')
                    ->state(fn (VisaApplication $record): string => $record->applicantProfile?->full_name ?? '—'),

                TextColumn::make('visaType.name')
                    ->label('Visa Type'),

                TextColumn::make('travel_date')
                    ->label('Travel Date')
                    ->date('M j, Y'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ApplicationStatus $state): string => $state->label())
                    ->color(fn (ApplicationStatus $state): string => $state->color()),
            ])
            ->headerActions([
                Action::make('viewAll')
                    ->label('View all →')
                    ->url(fn () => route('filament.admin.resources.visa-applications.index'))
                    ->color('primary'),
            ])
            ->paginated(false);
    }
}
