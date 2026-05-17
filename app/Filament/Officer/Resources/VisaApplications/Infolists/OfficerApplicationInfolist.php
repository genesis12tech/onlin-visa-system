<?php

namespace App\Filament\Officer\Resources\VisaApplications\Infolists;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OfficerApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                Section::make('Applicant')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('applicantProfile.full_name')
                            ->label('Full Name'),

                        TextEntry::make('applicantProfile.nationality.name')
                            ->label('Nationality')
                            ->placeholder('—'),

                        TextEntry::make('applicantProfile.date_of_birth')
                            ->label('Date of Birth')
                            ->date('M j, Y')
                            ->placeholder('—'),

                        TextEntry::make('passport_masked')
                            ->label('Passport')
                            ->state(fn (VisaApplication $record): string => $record->applicantProfile?->passport_number
                                ? '•••• '.substr($record->applicantProfile->passport_number, -4)
                                : '—'
                            ),
                    ]),

                Section::make('Application')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('tracking_number')
                            ->label('Reference'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (ApplicationStatus $state): string => $state->label())
                            ->color(fn (ApplicationStatus $state): string => $state->color()),

                        TextEntry::make('visaType.name')
                            ->label('Visa Type'),

                        TextEntry::make('submitted_at')
                            ->label('Submitted')
                            ->dateTime()
                            ->placeholder('Not submitted'),

                        TextEntry::make('travel_date')
                            ->label('Travel Date')
                            ->date('M j, Y')
                            ->placeholder('—'),

                        TextEntry::make('officer.name')
                            ->label('Assigned Officer')
                            ->placeholder('Unassigned'),
                    ]),
            ]),

            Section::make('Decision')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('decision_at')
                            ->label('Decided At')
                            ->dateTime()
                            ->placeholder('Pending'),

                        TextEntry::make('decision_reason')
                            ->label('Decision Reason')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                ])
                ->visible(fn (VisaApplication $record): bool => in_array(
                    $record->status,
                    [ApplicationStatus::Approved, ApplicationStatus::Rejected]
                )),

            Section::make('Latest Appointment')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('latestAppointment.appointment_at')
                            ->label('Date & Time')
                            ->dateTime('l, M j, Y \a\t g:i A')
                            ->placeholder('—'),

                        TextEntry::make('latestAppointment.location')
                            ->label('Location')
                            ->placeholder('—'),

                        TextEntry::make('latestAppointment.instructions')
                            ->label('Instructions')
                            ->placeholder('—'),
                    ]),
                ])
                ->visible(fn (VisaApplication $record): bool => $record->latestAppointment !== null),
        ]);
    }
}
