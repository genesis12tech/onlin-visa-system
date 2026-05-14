<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubmitApplication
{
    public function execute(VisaApplication $application, User $actor): VisaApplication
    {
        return DB::transaction(function () use ($application, $actor) {
            $blockingDocExists = $application->documents()
                ->whereIn('status', [
                    DocumentStatus::Pending->value,
                    DocumentStatus::Rejected->value,
                    DocumentStatus::Infected->value,
                ])
                ->exists();

            if ($blockingDocExists) {
                throw new \RuntimeException('All required documents must be uploaded before submission.');
            }

            $fromStatus = $application->status->value;

            $application->update([
                'status' => ApplicationStatus::Submitted,
                'submitted_at' => now(),
            ]);

            ApplicationStatusHistory::create([
                'visa_application_id' => $application->ulid,
                'from_status' => $fromStatus,
                'to_status' => ApplicationStatus::Submitted->value,
                'actor_id' => $actor->id,
                'created_at' => now(),
            ]);

            return $application->fresh();
        });
    }
}
