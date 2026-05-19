<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubmitInfoResponse
{
    public function execute(VisaApplication $application, User $actor): VisaApplication
    {
        if ($application->status !== ApplicationStatus::AdditionalInfoRequested) {
            throw new \RuntimeException('Application is not awaiting an info response.');
        }

        $fromStatus = $application->status->value;

        DB::transaction(function () use ($application, $actor, $fromStatus) {
            $infoNote = $application->notes()
                ->where('is_visible_to_applicant', true)
                ->whereNotNull('metadata')
                ->latest()
                ->first();

            $fieldsToUnlock = $infoNote?->metadata['fields_to_unlock'] ?? [];

            if (! empty($fieldsToUnlock)) {
                $answeredKeys = $application->answers()
                    ->whereIn('field_key', $fieldsToUnlock)
                    ->pluck('field_key')
                    ->all();

                foreach ($fieldsToUnlock as $key) {
                    if (! in_array($key, $answeredKeys, true)) {
                        throw new \RuntimeException('Please fill in all requested fields before submitting your response.');
                    }
                }
            }

            $hasBlockingDocs = $application->documents()
                ->whereIn('status', [
                    DocumentStatus::Pending->value,
                    DocumentStatus::Rejected->value,
                    DocumentStatus::Infected->value,
                ])
                ->exists();

            if ($hasBlockingDocs) {
                throw new \RuntimeException('All requested documents must be uploaded and accepted before submitting your response.');
            }

            $application->update(['status' => ApplicationStatus::UnderReview]);

            ApplicationStatusHistory::create([
                'visa_application_id' => $application->ulid,
                'from_status' => $fromStatus,
                'to_status' => ApplicationStatus::UnderReview->value,
                'actor_id' => $actor->id,
                'reason' => 'Applicant submitted additional information.',
                'created_at' => now(),
            ]);

            activity()
                ->causedBy($actor)
                ->performedOn($application)
                ->withProperties(['from' => $fromStatus, 'to' => ApplicationStatus::UnderReview->value])
                ->log('status_changed');
        });

        return $application->refresh();
    }
}
