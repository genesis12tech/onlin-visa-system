<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\VisaApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class WithdrawApplication
{
    private const WITHDRAWABLE = [
        ApplicationStatus::Draft,
        ApplicationStatus::Submitted,
        ApplicationStatus::PaymentPending,
        ApplicationStatus::PaymentCompleted,
        ApplicationStatus::UnderReview,
        ApplicationStatus::AdditionalInfoRequested,
    ];

    public static function run(VisaApplication $application, User $actor): void
    {
        if (! in_array($application->status, self::WITHDRAWABLE, strict: true)) {
            throw new \RuntimeException(
                "Cannot withdraw application with status: {$application->status->value}"
            );
        }

        DB::transaction(function () use ($application, $actor) {
            $fromStatus = $application->status->value;

            $application->update(['status' => ApplicationStatus::Withdrawn]);

            ApplicationStatusHistory::create([
                'visa_application_id' => $application->ulid,
                'from_status' => $fromStatus,
                'to_status' => ApplicationStatus::Withdrawn->value,
                'actor_id' => $actor->id,
                'created_at' => now(),
            ]);
        });
    }
}
