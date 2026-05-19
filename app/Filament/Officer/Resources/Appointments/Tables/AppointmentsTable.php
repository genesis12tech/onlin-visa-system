<?php

namespace App\Filament\Officer\Resources\Appointments\Tables;

use App\Domain\Applications\Enums\AppointmentStatus;
use App\Domain\Applications\Models\ApplicationAppointment;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AppointmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('visaApplication.tracking_number')
                    ->label('Application')
                    ->searchable()
                    ->url(fn (ApplicationAppointment $record): string => route(
                        'filament.officer.resources.applications.view',
                        $record->visa_application_id
                    )),

                TextColumn::make('visaApplication.applicantProfile.full_name')
                    ->label('Applicant'),

                TextColumn::make('appointment_at')
                    ->label('Appointment Date')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),

                TextColumn::make('location')
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (AppointmentStatus $state): string => $state->label())
                    ->color(fn (AppointmentStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(AppointmentStatus::cases())
                        ->mapWithKeys(fn (AppointmentStatus $s) => [$s->value => $s->label()])
                        ->toArray()),
            ])
            ->recordActions([
                Action::make('mark_completed')
                    ->label('Completed')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->visible(fn (ApplicationAppointment $record): bool => $record->status === AppointmentStatus::Scheduled)
                    ->requiresConfirmation()
                    ->action(fn (ApplicationAppointment $record) => $record->update(['status' => AppointmentStatus::Completed])),

                Action::make('mark_missed')
                    ->label('Missed')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn (ApplicationAppointment $record): bool => $record->status === AppointmentStatus::Scheduled)
                    ->requiresConfirmation()
                    ->action(fn (ApplicationAppointment $record) => $record->update(['status' => AppointmentStatus::Missed])),

                Action::make('mark_cancelled')
                    ->label('Cancelled')
                    ->color('gray')
                    ->icon('heroicon-o-x-mark')
                    ->visible(fn (ApplicationAppointment $record): bool => $record->status === AppointmentStatus::Scheduled)
                    ->requiresConfirmation()
                    ->action(fn (ApplicationAppointment $record) => $record->update(['status' => AppointmentStatus::Cancelled])),
            ])
            ->defaultSort('appointment_at', 'asc');
    }
}
