<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\VisaApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssignApplicationToOfficer
{
    public function execute(VisaApplication $application, User $officer, User $actor): VisaApplication
    {
        return DB::transaction(function () use ($application, $officer, $actor) {
            $fromStatus = $application->status->value;
            $toStatus = ApplicationStatus::UnderReview->value;

            $application->update([
                'assigned_officer_id' => $officer->id,
                'status' => ApplicationStatus::UnderReview,
            ]);

            ApplicationStatusHistory::create([
                'visa_application_id' => $application->ulid,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'actor_id' => $actor->id,
                'reason' => "Assigned to {$officer->name}",
                'created_at' => now(),
            ]);

            return $application->fresh();
        });
    }
}
