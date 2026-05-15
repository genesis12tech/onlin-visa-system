<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\VisaApplication;
use App\Models\User;
use App\Notifications\AdditionalInfoRequestedNotification;
use Illuminate\Support\Facades\DB;

class RequestAdditionalInformation
{
    public function execute(VisaApplication $application, User $actor, string $message): VisaApplication
    {
        $fromStatus = $application->status->value;

        DB::transaction(function () use ($application, $actor, $message, $fromStatus) {
            $application->update([
                'status' => ApplicationStatus::AdditionalInfoRequested,
            ]);

            ApplicationStatusHistory::create([
                'visa_application_id' => $application->ulid,
                'from_status' => $fromStatus,
                'to_status' => ApplicationStatus::AdditionalInfoRequested->value,
                'actor_id' => $actor->id,
                'reason' => $message,
                'created_at' => now(),
            ]);

            activity()
                ->causedBy($actor)
                ->performedOn($application)
                ->withProperties(['from' => $fromStatus, 'to' => ApplicationStatus::AdditionalInfoRequested->value])
                ->log('status_changed');
        });

        $application->refresh();

        $application->load('applicantProfile.user');
        $applicantUser = $application->applicantProfile?->user;

        if ($applicantUser) {
            $applicantUser->notify(new AdditionalInfoRequestedNotification($application, $message));
        }

        return $application;
    }
}
