<?php

namespace App\Filament\Resources\VisaApplications\Pages;

use App\Domain\Applications\Actions\ApproveApplication;
use App\Domain\Applications\Actions\RejectApplication;
use App\Domain\Applications\Actions\RequestAdditionalInformation;
use App\Domain\Applications\Actions\ScheduleAppointment;
use App\Filament\Resources\VisaApplications\Infolists\VisaApplicationInfolist;
use App\Filament\Resources\VisaApplications\VisaApplicationResource;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ViewVisaApplication extends ViewRecord
{
    protected static string $resource = VisaApplicationResource::class;

    public function infolist(Schema $schema): Schema
    {
        return VisaApplicationInfolist::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->authorize(fn (): bool => auth()->user()?->can('approve', $this->record) ?? false)
                ->action(function () {
                    try {
                        (new ApproveApplication)->execute($this->record, auth()->user());
                        Notification::make()->success()->title('Application approved')->send();
                        $this->refreshFormData(['status', 'decision_at', 'decision_reason']);
                    } catch (\RuntimeException $e) {
                        Notification::make()->danger()->title('Cannot approve')->body($e->getMessage())->send();
                    }
                }),

            Action::make('reject')
                ->label('Reject')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->authorize(fn (): bool => auth()->user()?->can('reject', $this->record) ?? false)
                ->schema([
                    Textarea::make('reason')
                        ->label('Rejection reason')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    (new RejectApplication)->execute($this->record, auth()->user(), $data['reason']);
                    $this->refreshFormData(['status', 'decision_at', 'decision_reason']);
                })
                ->successNotificationTitle('Application rejected'),

            Action::make('request_info')
                ->label('Request Info')
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color('warning')
                ->authorize(fn (): bool => auth()->user()?->can('requestAdditionalInfo', $this->record) ?? false)
                ->schema([
                    Textarea::make('message')
                        ->label('Message to applicant')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    (new RequestAdditionalInformation)->execute($this->record, auth()->user(), $data['message']);
                    $this->refreshFormData(['status']);
                })
                ->successNotificationTitle('Information requested'),

            Action::make('schedule_appointment')
                ->label('Schedule Appointment')
                ->icon(Heroicon::OutlinedCalendar)
                ->color('info')
                ->authorize(fn (): bool => auth()->user()?->can('scheduleAppointment', $this->record) ?? false)
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
                ->action(fn (array $data) => (new ScheduleAppointment)->execute(
                    $this->record,
                    auth()->user(),
                    Carbon::parse($data['appointment_at']),
                    $data['location'] ?? null,
                    $data['instructions'] ?? null,
                ))
                ->successNotificationTitle('Appointment scheduled'),
        ];
    }
}
