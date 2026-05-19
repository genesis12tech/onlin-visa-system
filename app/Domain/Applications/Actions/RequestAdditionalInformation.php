<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Models\User;
use App\Notifications\AdditionalInfoRequestedNotification;
use Illuminate\Support\Facades\DB;

class RequestAdditionalInformation
{
    /** @var list<string> */
    private const IDENTITY_FIELDS = [
        'personal.first_name', 'personal.last_name', 'personal.date_of_birth',
        'personal.nationality', 'personal.passport_number',
    ];

    public function execute(
        VisaApplication $application,
        User $actor,
        string $message,
        array $documentsToResubmit = [],
        array $fieldsToUnlock = [],
        int $deadlineDays = 7,
    ): VisaApplication {
        $blocked = array_intersect($fieldsToUnlock, self::IDENTITY_FIELDS);
        if (! empty($blocked)) {
            throw new \InvalidArgumentException('Identity fields cannot be unlocked: '.implode(', ', $blocked));
        }

        $fromStatus = $application->status->value;

        DB::transaction(function () use ($application, $actor, $message, $fromStatus, $documentsToResubmit, $fieldsToUnlock, $deadlineDays) {
            if (! empty($documentsToResubmit)) {
                ApplicationDocument::whereIn('ulid', $documentsToResubmit)
                    ->where('visa_application_id', $application->ulid)
                    ->update(['status' => DocumentStatus::Pending->value]);
            }

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

            (new AddReviewNote)->execute(
                $application,
                $actor,
                $message,
                true,
                [
                    'fields_to_unlock' => $fieldsToUnlock,
                    'deadline_days' => $deadlineDays,
                    'deadline_date' => now()->addDays($deadlineDays)->toDateString(),
                ],
            );
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
