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
            // Lock the row so concurrent submission attempts serialize rather than both
            // passing the Draft status check on a stale in-memory value.
            $locked = VisaApplication::where('ulid', $application->ulid)->lockForUpdate()->first();

            if (! $locked || $locked->status !== ApplicationStatus::Draft) {
                $currentStatus = $locked?->status->value ?? 'unknown';
                throw new \RuntimeException("Application is not in a submittable state: {$currentStatus}");
            }

            $blockingDocExists = $locked->documents()
                ->whereIn('status', [
                    DocumentStatus::Pending->value,
                    DocumentStatus::Rejected->value,
                    DocumentStatus::Infected->value,
                ])
                ->exists();

            if ($blockingDocExists) {
                throw new \RuntimeException('All required documents must be uploaded before submission.');
            }

            $fromStatus = $locked->status->value;

            $locked->update([
                'status' => ApplicationStatus::Submitted,
                'submitted_at' => now(),
            ]);

            ApplicationStatusHistory::create([
                'visa_application_id' => $locked->ulid,
                'from_status' => $fromStatus,
                'to_status' => ApplicationStatus::Submitted->value,
                'actor_id' => $actor->id,
                'created_at' => now(),
            ]);

            ApplicationSnapshot::firstOrCreate(
                ['visa_application_id' => $locked->ulid],
                [
                    'snapshot_data' => [
                        'tracking_number' => $locked->tracking_number,
                        'visa_type' => $locked->visaType?->toArray(),
                        'form_template_id' => $locked->form_template_id,
                        'answers' => $locked->answers()->get(['field_key', 'value'])->toArray(),
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
