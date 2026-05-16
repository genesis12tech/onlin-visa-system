<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Jobs\GenerateDecisionLetterPdf;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Models\User;
use App\Notifications\ApplicationApprovedNotification;
use Illuminate\Support\Facades\DB;

class ApproveApplication
{
    public function execute(VisaApplication $application, User $actor, ?string $reason = null): VisaApplication
    {
        $this->guardDocumentReadiness($application);

        $fromStatus = $application->status->value;

        DB::transaction(function () use ($application, $actor, $reason, $fromStatus) {
            $application->update([
                'status' => ApplicationStatus::Approved,
                'decision_at' => now(),
                'decision_reason' => $reason,
            ]);

            ApplicationStatusHistory::create([
                'visa_application_id' => $application->ulid,
                'from_status' => $fromStatus,
                'to_status' => ApplicationStatus::Approved->value,
                'actor_id' => $actor->id,
                'reason' => $reason,
                'created_at' => now(),
            ]);

            activity()
                ->causedBy($actor)
                ->performedOn($application)
                ->withProperties(['from' => $fromStatus, 'to' => ApplicationStatus::Approved->value])
                ->log('status_changed');
        });

        $application->refresh();

        $this->notifyApplicant($application);

        GenerateDecisionLetterPdf::dispatch($application->ulid)->onQueue('pdfs');

        return $application;
    }

    private function guardDocumentReadiness(VisaApplication $application): void
    {
        $application->load('visaType.documentRequirements', 'documents');

        $requiredTypeIds = $application->visaType
            ->documentRequirements
            ->where('is_required', true)
            ->pluck('document_type_id');

        if ($requiredTypeIds->isEmpty()) {
            return;
        }

        $acceptedTypeIds = $application->documents
            ->where('status', DocumentStatus::Accepted)
            ->pluck('document_type_id');

        $missing = $requiredTypeIds->diff($acceptedTypeIds);

        if ($missing->isNotEmpty()) {
            throw new \RuntimeException('Cannot approve: all required documents must be accepted first.');
        }
    }

    private function notifyApplicant(VisaApplication $application): void
    {
        $application->load('applicantProfile.user');
        $applicantUser = $application->applicantProfile?->user;

        if ($applicantUser) {
            $applicantUser->notify(new ApplicationApprovedNotification($application));
        }
    }
}
