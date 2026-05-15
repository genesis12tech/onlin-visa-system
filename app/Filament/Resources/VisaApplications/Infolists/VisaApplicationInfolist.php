<?php

namespace App\Filament\Resources\VisaApplications\Infolists;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VisaApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Application Details')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('tracking_number')
                            ->label('Reference'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (ApplicationStatus $state): string => $state->label())
                            ->color(fn (ApplicationStatus $state): string => $state->color()),

                        TextEntry::make('submitted_at')
                            ->label('Submitted')
                            ->dateTime()
                            ->placeholder('Not submitted'),
                    ]),

                    Grid::make(3)->schema([
                        TextEntry::make('visaType.name')
                            ->label('Visa Type'),

                        TextEntry::make('officer.name')
                            ->label('Assigned Officer')
                            ->placeholder('Unassigned'),

                        TextEntry::make('travel_date')
                            ->label('Travel Date')
                            ->date('M j, Y')
                            ->placeholder('—'),
                    ]),
                ]),

            Section::make('Applicant')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('applicantProfile.full_name')
                            ->label('Full Name'),

                        TextEntry::make('applicantProfile.nationality.name')
                            ->label('Nationality'),

                        TextEntry::make('applicantProfile.date_of_birth')
                            ->label('Date of Birth')
                            ->date('M j, Y'),
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
        ]);
    }
}
