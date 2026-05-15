<?php

namespace App\Filament\Resources\VisaApplications\Tables;

use App\Domain\Applications\Actions\ApproveApplication;
use App\Domain\Applications\Actions\AssignApplicationToOfficer;
use App\Domain\Applications\Actions\RejectApplication;
use App\Domain\Applications\Actions\RequestAdditionalInformation;
use App\Domain\Applications\Actions\ScheduleAppointment;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use App\Jobs\ExportApplicationsJob;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class VisaApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
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

                TextColumn::make('nationality')
                    ->label('Nationality')
                    ->state(fn (VisaApplication $record): string => $record->applicantProfile?->nationality?->name ?? '—'),

                TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->date('M j, Y')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ApplicationStatus $state): string => $state->label())
                    ->color(fn (ApplicationStatus $state): string => $state->color()),

                TextColumn::make('officer.name')
                    ->label('Assigned To')
                    ->default('Unassigned'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(ApplicationStatus::cases())->mapWithKeys(
                        fn (ApplicationStatus $s) => [$s->value => $s->label()]
                    )->toArray()),

                SelectFilter::make('visa_type_id')
                    ->label('Visa Type')
                    ->relationship('visaType', 'name'),

                SelectFilter::make('assigned_officer_id')
                    ->label('Assigned Officer')
                    ->relationship('officer', 'name'),

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
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->url(fn (VisaApplication $record): string => route('filament.admin.resources.visa-applications.view', $record)),

                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->authorize(fn (VisaApplication $record): bool => auth()->user()?->can('approve', $record) ?? false)
                    ->action(function (VisaApplication $record) {
                        try {
                            (new ApproveApplication)->execute($record, auth()->user());
                            Notification::make()->success()->title('Application approved')->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()->danger()->title('Cannot approve')->body($e->getMessage())->send();
                        }
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->authorize(fn (VisaApplication $record): bool => auth()->user()?->can('reject', $record) ?? false)
                    ->schema([
                        Textarea::make('reason')
                            ->label('Rejection reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(fn (VisaApplication $record, array $data) => (new RejectApplication)->execute($record, auth()->user(), $data['reason']))
                    ->successNotificationTitle('Application rejected'),

                Action::make('request_info')
                    ->label('Request Info')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->color('warning')
                    ->authorize(fn (VisaApplication $record): bool => auth()->user()?->can('requestAdditionalInfo', $record) ?? false)
                    ->schema([
                        Textarea::make('message')
                            ->label('Message to applicant')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(fn (VisaApplication $record, array $data) => (new RequestAdditionalInformation)->execute($record, auth()->user(), $data['message']))
                    ->successNotificationTitle('Information requested'),

                Action::make('schedule_appointment')
                    ->label('Schedule Appointment')
                    ->icon(Heroicon::OutlinedCalendar)
                    ->color('info')
                    ->authorize(fn (VisaApplication $record): bool => auth()->user()?->can('scheduleAppointment', $record) ?? false)
                    ->schema([
                        DateTimePicker::make('appointment_at')
                            ->label('Appointment Date & Time')
                            ->required()
                            ->minDate(now()),
                        TextInput::make('location')
                            ->label('Location')
                            ->nullable(),
                        Textarea::make('instructions')
                            ->label('Instructions for applicant')
                            ->nullable()
                            ->rows(3),
                    ])
                    ->action(fn (VisaApplication $record, array $data) => (new ScheduleAppointment)->execute(
                        $record,
                        auth()->user(),
                        Carbon::parse($data['appointment_at']),
                        $data['location'] ?? null,
                        $data['instructions'] ?? null,
                    ))
                    ->successNotificationTitle('Appointment scheduled'),
            ])
            ->bulkActions([
                BulkAction::make('assign')
                    ->label('Assign to officer')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->authorize(fn (): bool => auth()->user()?->can('assign', VisaApplication::class) ?? false)
                    ->schema([
                        Select::make('officer_id')
                            ->label('Officer')
                            ->options(User::role(['case_officer', 'senior_officer'])->pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $officer = User::findOrFail($data['officer_id']);
                        $records->each(fn (VisaApplication $record) => (new AssignApplicationToOfficer)->execute($record, $officer, auth()->user()));
                    })
                    ->successNotificationTitle('Applications assigned'),

                BulkAction::make('export')
                    ->label('Export to CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->authorize(fn (): bool => auth()->user()?->can('export', VisaApplication::class) ?? false)
                    ->action(function (Collection $records): void {
                        ExportApplicationsJob::dispatch(
                            $records->pluck('ulid')->toArray(),
                            auth()->id(),
                        );
                    })
                    ->successNotificationTitle('Export queued — you will receive a download link shortly'),
            ])
            ->recordUrl(null)
            ->recordAction(null)
            ->searchPlaceholder('Search reference, applicant…')
            ->paginated([10, 25, 50]);
    }
}
