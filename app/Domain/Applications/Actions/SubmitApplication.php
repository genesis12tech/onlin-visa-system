<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Jobs\GenerateApplicationSummaryPdf;
use App\Domain\Applications\Models\ApplicationSnapshot;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Models\User;
use App\Notifications\ApplicationSubmittedNotification;
use Illuminate\Support\Facades\DB;

class SubmitApplication
{
    public function execute(VisaApplication $application, User $actor): VisaApplication
    {
        DB::transaction(function () use ($application, $actor) {
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

            ApplicationSnapshot::firstOrCreate(
                ['visa_application_id' => $application->ulid],
                [
                    'snapshot_data' => [
                        'tracking_number' => $application->tracking_number,
                        'visa_type' => $application->visaType?->toArray(),
                        'form_template_id' => $application->form_template_id,
                        'answers' => $application->answers()->get(['field_key', 'value'])->toArray(),
                        'submitted_at' => now()->toISOString(),
                    ],
                    'created_at' => now(),
                ],
            );
        });

        $application->refresh();

        $application->load('applicantProfile.user');
        $applicantUser = $application->applicantProfile?->user;

        if ($applicantUser) {
            $applicantUser->notify(new ApplicationSubmittedNotification($application));
        }

        GenerateApplicationSummaryPdf::dispatch($application->ulid)->onQueue('pdfs');

        return $application;
    }
}
