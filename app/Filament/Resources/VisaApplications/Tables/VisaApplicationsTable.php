<?php

namespace App\Filament\Resources\VisaApplications\Tables;

use App\Models\VisaApplication;
use App\Support\MockDataService;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VisaApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->records(function () use ($table) {
                $livewire = $table->getLivewire();
                $data = MockDataService::applications();

                $activeTab = $livewire->activeTab ?? 'all';
                if ($activeTab && $activeTab !== 'all') {
                    $data = array_values(array_filter($data, fn ($row) => $row['status'] === $activeTab));
                }

                $search = strtolower($livewire->tableSearch ?? '');
                if ($search !== '') {
                    $data = array_values(array_filter(
                        $data,
                        fn ($row) => str_contains(strtolower($row['name']), $search)
                            || str_contains(strtolower($row['reference']), $search),
                    ));
                }

                return $data;
            })
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->color('info'),

                TextColumn::make('name')
                    ->label('Applicant'),

                TextColumn::make('visa_type')
                    ->label('Visa Type'),

                TextColumn::make('nationality')
                    ->label('Nationality'),

                TextColumn::make('travel_date')
                    ->label('Travel Date')
                    ->state(fn (array $record): string => date('M j, Y', strtotime($record['travel_date']))),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (array $record): string => VisaApplication::statusLabel($record['status']))
                    ->color(fn (string $state): string => VisaApplication::statusColor($state)),

                TextColumn::make('assigned_to')
                    ->label('Assigned To')
                    ->state(fn (array $record): string => $record['assigned_to'] ?? 'Unassigned'),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->url('#')
                    ->color('gray'),
                Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->action(fn () => null),
                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->action(fn () => null),
            ])
            ->recordUrl(null)
            ->recordAction(null)
            ->toolbarActions([])
            ->searchPlaceholder('Search reference, name…')
            ->paginated([10, 25, 50]);
    }
}
