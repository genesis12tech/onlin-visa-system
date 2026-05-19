<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Enums\RejectionReason;
use App\Domain\Applications\Jobs\GenerateDecisionLetterPdf;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\VisaApplication;
use App\Models\User;
use App\Notifications\ApplicationRejectedNotification;
use Illuminate\Support\Facades\DB;

class RejectApplication
{
    public function execute(
        VisaApplication $application,
        User $actor,
        string $reason,
        ?RejectionReason $rejectionReason = null,
        ?string $explanationForApplicant = null,
        ?string $internalNotes = null,
    ): VisaApplication {
        $storedReason = $rejectionReason?->value ?? $reason;
        $fromStatus = $application->status->value;

        DB::transaction(function () use ($application, $actor, $storedReason, $fromStatus, $explanationForApplicant, $internalNotes) {
            $application->update([
                'status' => ApplicationStatus::Rejected,
                'decision_at' => now(),
                'decision_reason' => $storedReason,
            ]);

            ApplicationStatusHistory::create([
                'visa_application_id' => $application->ulid,
                'from_status' => $fromStatus,
                'to_status' => ApplicationStatus::Rejected->value,
                'actor_id' => $actor->id,
                'reason' => $storedReason,
                'created_at' => now(),
            ]);

            activity()
                ->causedBy($actor)
                ->performedOn($application)
                ->withProperties(['from' => $fromStatus, 'to' => ApplicationStatus::Rejected->value, 'reason' => $storedReason])
                ->log('status_changed');

            if ($explanationForApplicant) {
                (new AddReviewNote)->execute($application, $actor, $explanationForApplicant, true);
            }

            if ($internalNotes) {
                (new AddReviewNote)->execute($application, $actor, $internalNotes, false);
            }
        });

        $application->refresh();

        $application->load('applicantProfile.user');
        $applicantUser = $application->applicantProfile?->user;

        if ($applicantUser) {
            $applicantUser->notify(new ApplicationRejectedNotification($application));
        }

        // TODO(M6): GenerateDecisionLetterPdf job body not yet implemented — dispatched but handler is a stub.
        GenerateDecisionLetterPdf::dispatch($application->ulid)->onQueue('pdfs');

        return $application;
    }
}
