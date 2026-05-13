<?php

namespace App\Filament\Widgets;

use App\Models\VisaApplication;
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
            ->records(fn () => [
                ['reference' => 'VA-2024-A1F3K2', 'applicant' => 'Arjun Mehta',     'visa_type' => 'Tourist',  'submitted' => 'Jan 14, 2025', 'status' => 'Submitted'],
                ['reference' => 'VA-2024-B2G4L3', 'applicant' => 'Sofia Chen',      'visa_type' => 'Student',  'submitted' => 'Feb 1, 2025',  'status' => 'Under review'],
                ['reference' => 'VA-2024-C3H5M4', 'applicant' => 'James Okonkwo',   'visa_type' => 'Work',     'submitted' => 'Jan 20, 2025', 'status' => 'Approved'],
                ['reference' => 'VA-2024-D4I6N5', 'applicant' => 'Maria Santos',    'visa_type' => 'Tourist',  'submitted' => 'Mar 5, 2025',  'status' => 'Docs required'],
                ['reference' => 'VA-2024-E5J7O6', 'applicant' => 'Ahmed Al-Rashid', 'visa_type' => 'Business', 'submitted' => 'Jan 30, 2025', 'status' => 'Under review'],
            ])
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->color('info'),
                TextColumn::make('applicant')
                    ->label('Applicant'),
                TextColumn::make('visa_type')
                    ->label('Visa Type'),
                TextColumn::make('submitted')
                    ->label('Submitted'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
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
