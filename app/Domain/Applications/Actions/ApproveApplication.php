<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\VisaApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApproveApplication
{
    public function execute(VisaApplication $application, User $actor, ?string $reason = null): VisaApplication
    {
        return DB::transaction(function () use ($application, $actor, $reason) {
            $fromStatus = $application->status->value;

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

            return $application->fresh();
        });
    }
}
