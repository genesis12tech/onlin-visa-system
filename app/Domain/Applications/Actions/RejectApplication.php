<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Jobs\GenerateDecisionLetterPdf;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\VisaApplication;
use App\Models\User;
use App\Notifications\ApplicationRejectedNotification;
use Illuminate\Support\Facades\DB;

class RejectApplication
{
    public function execute(VisaApplication $application, User $actor, string $reason): VisaApplication
    {
        $fromStatus = $application->status->value;

        DB::transaction(function () use ($application, $actor, $reason, $fromStatus) {
            $application->update([
                'status' => ApplicationStatus::Rejected,
                'decision_at' => now(),
                'decision_reason' => $reason,
            ]);

            ApplicationStatusHistory::create([
                'visa_application_id' => $application->ulid,
                'from_status' => $fromStatus,
                'to_status' => ApplicationStatus::Rejected->value,
                'actor_id' => $actor->id,
                'reason' => $reason,
                'created_at' => now(),
            ]);

            activity()
                ->causedBy($actor)
                ->performedOn($application)
                ->withProperties(['from' => $fromStatus, 'to' => ApplicationStatus::Rejected->value, 'reason' => $reason])
                ->log('status_changed');
        });

        $application->refresh();

        $application->load('applicantProfile.user');
        $applicantUser = $application->applicantProfile?->user;

        if ($applicantUser) {
            $applicantUser->notify(new ApplicationRejectedNotification($application));
        }

        GenerateDecisionLetterPdf::dispatch($application->ulid)->onQueue('pdfs');

        return $application;
    }
}
