<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Jobs\GenerateAppointmentConfirmationPdf;
use App\Domain\Applications\Models\ApplicationAppointment;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\VisaApplication;
use App\Models\User;
use App\Notifications\AppointmentScheduledNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ScheduleAppointment
{
    public function execute(
        VisaApplication $application,
        User $actor,
        Carbon $appointmentAt,
        ?string $location = null,
        ?string $instructions = null,
    ): ApplicationAppointment {
        $fromStatus = $application->status->value;

        $appointment = DB::transaction(function () use ($application, $actor, $appointmentAt, $location, $instructions, $fromStatus) {
            $appointment = ApplicationAppointment::create([
                'visa_application_id' => $application->ulid,
                'created_by' => $actor->id,
                'appointment_at' => $appointmentAt,
                'location' => $location,
                'instructions' => $instructions,
            ]);

            ApplicationStatusHistory::create([
                'visa_application_id' => $application->ulid,
                'from_status' => $fromStatus,
                'to_status' => $fromStatus,
                'actor_id' => $actor->id,
                'reason' => 'Appointment scheduled for '.$appointmentAt->format('Y-m-d H:i'),
                'created_at' => now(),
            ]);

            activity()
                ->causedBy($actor)
                ->performedOn($application)
                ->withProperties(['appointment_at' => $appointmentAt->toIso8601String()])
                ->log('appointment_scheduled');

            return $appointment;
        });

        $application->load('applicantProfile.user');
        $applicantUser = $application->applicantProfile?->user;

        if ($applicantUser) {
            $applicantUser->notify(new AppointmentScheduledNotification($application, $appointment));
        }

        GenerateAppointmentConfirmationPdf::dispatch($appointment->ulid)->onQueue('pdfs');

        return $appointment;
    }
}
