<?php

namespace App\Filament\Officer\Resources\VisaApplications\Pages;

use App\Domain\Applications\Actions\ApproveApplication;
use App\Domain\Applications\Actions\RejectApplication;
use App\Domain\Applications\Actions\RequestAdditionalInformation;
use App\Domain\Applications\Actions\ScheduleAppointment;
use App\Domain\Applications\Enums\RejectionReason;
use App\Domain\Applications\Models\ServiceLocation;
use App\Filament\Officer\Resources\VisaApplications\Infolists\OfficerApplicationInfolist;
use App\Filament\Officer\Resources\VisaApplications\OfficerVisaApplicationResource;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ViewOfficerApplication extends ViewRecord
{
    protected static string $resource = OfficerVisaApplicationResource::class;

    public function infolist(Schema $schema): Schema
    {
        return OfficerApplicationInfolist::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->authorize(fn (): bool => auth()->user()?->can('approve', $this->record) ?? false)
                ->schema([
                    Select::make('validity_period')
                        ->label('Validity period')
                        ->options([
                            '3_months' => '3 months',
                            '6_months' => '6 months',
                            '12_months' => '12 months',
                        ])
                        ->default('12_months')
                        ->required(),
                    Select::make('entry_type')
                        ->label('Entry type')
                        ->options([
                            'single' => 'Single entry',
                            'multiple' => 'Multiple entry',
                        ])
                        ->default('single')
                        ->required(),
                    Textarea::make('internal_notes')
                        ->label('Internal notes (not shown to applicant)')
                        ->rows(2),
                ])
                ->requiresConfirmation()
                ->modalHeading('Confirm Approval')
                ->modalDescription(fn () => "Approving {$this->record->tracking_number}. This will generate a decision letter and notify the applicant.")
                ->action(function (array $data) {
                    try {
                        (new ApproveApplication)->execute(
                            $this->record,
                            auth()->user(),
                            null,
                            $data['validity_period'],
                            $data['entry_type'],
                            $data['internal_notes'] ?? null,
                        );
                        Notification::make()->success()->title('Application approved')->send();
                        $this->redirect(OfficerVisaApplicationResource::getUrl('index'));
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
                    Select::make('rejection_reason')
                        ->label('Rejection reason')
                        ->options(collect(RejectionReason::cases())->mapWithKeys(
                            fn (RejectionReason $r) => [$r->value => $r->label()]
                        ))
                        ->required(),
                    Textarea::make('explanation_for_applicant')
                        ->label('Explanation (sent to applicant)')
                        ->required()
                        ->rows(3),
                    Textarea::make('internal_notes')
                        ->label('Internal notes (not shared with applicant)')
                        ->rows(2),
                ])
                ->requiresConfirmation()
                ->action(function (array $data) {
                    (new RejectApplication)->execute(
                        $this->record,
                        auth()->user(),
                        $data['rejection_reason'],
                        RejectionReason::from($data['rejection_reason']),
                        $data['explanation_for_applicant'],
                        $data['internal_notes'] ?? null,
                    );
                    Notification::make()->warning()->title('Application rejected')->send();
                    $this->redirect(OfficerVisaApplicationResource::getUrl('index'));
                }),

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
                    CheckboxList::make('documents_to_resubmit')
                        ->label('Documents to resubmit')
                        ->options(fn () => $this->record->documents()
                            ->with('documentType')
                            ->get()
                            ->pluck('documentType.name', 'ulid')
                        ),
                    CheckboxList::make('fields_to_unlock')
                        ->label('Application fields to unlock for editing')
                        ->options([
                            'employment.employer_name' => 'Employer name',
                            'employment.job_title' => 'Job title',
                            'travel.purpose' => 'Purpose of visit',
                            'travel.accommodation' => 'Accommodation address',
                        ]),
                    Select::make('deadline_days')
                        ->label('Response deadline')
                        ->options([3 => '3 days (urgent)', 7 => '7 days (default)', 14 => '14 days'])
                        ->default(7)
                        ->required(),
                ])
                ->action(function (array $data) {
                    (new RequestAdditionalInformation)->execute(
                        $this->record,
                        auth()->user(),
                        $data['message'],
                        $data['documents_to_resubmit'] ?? [],
                        $data['fields_to_unlock'] ?? [],
                        (int) $data['deadline_days'],
                    );
                    Notification::make()->success()->title('Information request sent')->send();
                }),

            Action::make('schedule_appointment')
                ->label('Schedule Appointment')
                ->icon(Heroicon::OutlinedCalendar)
                ->color('info')
                ->authorize(fn (): bool => auth()->user()?->can('scheduleAppointment', $this->record) ?? false)
                ->schema([
                    Select::make('type')
                        ->label('Appointment type')
                        ->options([
                            'biometrics' => 'Biometrics',
                            'interview' => 'Interview',
                            'document_drop' => 'Document drop-off',
                        ])
                        ->required(),
                    Select::make('location_id')
                        ->label('Location')
                        ->options(ServiceLocation::active()->pluck('name', 'ulid'))
                        ->searchable(),
                    DateTimePicker::make('appointment_at')
                        ->label('Date & Time')
                        ->required()
                        ->minDate(now()),
                    Textarea::make('instructions')
                        ->label('Instructions for applicant')
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    (new ScheduleAppointment)->execute(
                        $this->record,
                        auth()->user(),
                        Carbon::parse($data['appointment_at']),
                        $data['location_id'] ?? null,
                        $data['instructions'] ?? null,
                        $data['type'],
                    );
                    Notification::make()->success()->title('Appointment scheduled')->send();
                }),
        ];
    }
}
