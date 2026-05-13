<?php

namespace App\Filament\Widgets;

use App\Models\VisaApplication;
use App\Support\MockDataService;
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
            ->records(fn () => MockDataService::recentApplications(5))
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->color('info'),
                TextColumn::make('name')
                    ->label('Applicant'),
                TextColumn::make('visa_type')
                    ->label('Visa Type'),
                TextColumn::make('travel_date')
                    ->label('Travel Date')
                    ->state(fn (array $record): string => date('M j, Y', strtotime($record['travel_date']))),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (array $record): string => VisaApplication::statusLabel($record['status']))
                    ->color(fn (string $state): string => VisaApplication::statusColor($state)),
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
