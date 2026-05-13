<?php

namespace App\Filament\Resources\VisaApplications\Tables;

use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VisaApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->records(fn () => [
                ['reference' => 'VA-2024-A1F3K2', 'applicant' => 'Arjun Mehta',     'visa_type' => 'Tourist',  'nationality' => 'Indian',     'travel_date' => 'Jan 14, 2025', 'status' => 'Submitted',     'assigned_to' => 'Unassigned'],
                ['reference' => 'VA-2024-B2G4L3', 'applicant' => 'Sofia Chen',      'visa_type' => 'Student',  'nationality' => 'Chinese',    'travel_date' => 'Feb 1, 2025',  'status' => 'Under review',  'assigned_to' => 'Priya Mehta'],
                ['reference' => 'VA-2024-C3H5M4', 'applicant' => 'James Okonkwo',   'visa_type' => 'Work',     'nationality' => 'Nigerian',   'travel_date' => 'Jan 20, 2025', 'status' => 'Approved',      'assigned_to' => 'Rahul Sharma'],
                ['reference' => 'VA-2024-D4I6N5', 'applicant' => 'Maria Santos',    'visa_type' => 'Tourist',  'nationality' => 'Brazilian',  'travel_date' => 'Mar 5, 2025',  'status' => 'Docs required', 'assigned_to' => 'Anita Desai'],
                ['reference' => 'VA-2024-E5J7O6', 'applicant' => 'Ahmed Al-Rashid', 'visa_type' => 'Business', 'nationality' => 'Saudi',      'travel_date' => 'Jan 30, 2025', 'status' => 'Under review',  'assigned_to' => 'Mohammed Khan'],
                ['reference' => 'VA-2024-F6K8P7', 'applicant' => 'Priya Nair',      'visa_type' => 'Student',  'nationality' => 'Indian',     'travel_date' => 'Feb 15, 2025', 'status' => 'Submitted',     'assigned_to' => 'Unassigned'],
                ['reference' => 'VA-2024-G7L9Q8', 'applicant' => 'Lucas Müller',    'visa_type' => 'Tourist',  'nationality' => 'German',     'travel_date' => 'Dec 28, 2024', 'status' => 'Approved',      'assigned_to' => 'Priya Mehta'],
                ['reference' => 'VA-2024-H8M0R9', 'applicant' => 'Yuki Tanaka',     'visa_type' => 'Medical',  'nationality' => 'Japanese',   'travel_date' => 'Jan 10, 2025', 'status' => 'Rejected',      'assigned_to' => 'Rahul Sharma'],
                ['reference' => 'VA-2024-I9N1S0', 'applicant' => 'Emma Wilson',     'visa_type' => 'Work',     'nationality' => 'Australian', 'travel_date' => 'Feb 5, 2025',  'status' => 'Submitted',     'assigned_to' => 'Unassigned'],
                ['reference' => 'VA-2024-J0O2T1', 'applicant' => 'Ravi Krishnan',   'visa_type' => 'Business', 'nationality' => 'Indian',     'travel_date' => 'Jan 22, 2025', 'status' => 'Under review',  'assigned_to' => 'Anita Desai'],
            ])
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->color('info'),

                TextColumn::make('applicant')
                    ->label('Applicant'),

                TextColumn::make('visa_type')
                    ->label('Visa Type'),

                TextColumn::make('nationality')
                    ->label('Nationality'),

                TextColumn::make('travel_date')
                    ->label('Travel Date'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Submitted' => 'info',
                        'Under review' => 'warning',
                        'Approved' => 'success',
                        'Docs required' => 'primary',
                        'Rejected' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('assigned_to')
                    ->label('Assigned To'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'Submitted' => 'Submitted',
                        'Under review' => 'In review',
                        'Approved' => 'Approved',
                        'Rejected' => 'Rejected',
                    ]),
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
            ->toolbarActions([])
            ->searchPlaceholder('Search reference, name…')
            ->paginated([10, 25, 50]);
    }
}
