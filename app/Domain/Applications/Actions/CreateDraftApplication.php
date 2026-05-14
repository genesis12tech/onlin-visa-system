<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\VisaTypeDocumentRequirement;
use App\Domain\Identity\Models\ApplicantProfile;
use Illuminate\Support\Facades\DB;

class CreateDraftApplication
{
    public function __construct(private readonly GenerateTrackingNumber $generateTrackingNumber) {}

    public function execute(
        ApplicantProfile $applicantProfile,
        VisaType $visaType,
        FormTemplate $formTemplate,
        ?string $travelDate = null,
    ): VisaApplication {
        return DB::transaction(function () use ($applicantProfile, $visaType, $formTemplate, $travelDate) {
            $application = VisaApplication::create([
                'tracking_number' => $this->generateTrackingNumber->execute(),
                'applicant_profile_id' => $applicantProfile->ulid,
                'visa_type_id' => $visaType->ulid,
                'form_template_id' => $formTemplate->ulid,
                'status' => ApplicationStatus::Draft,
                'travel_date' => $travelDate,
            ]);

            $requirements = VisaTypeDocumentRequirement::where('visa_type_id', $visaType->ulid)
                ->orderBy('display_order')
                ->get();

            foreach ($requirements as $requirement) {
                ApplicationDocument::create([
                    'visa_application_id' => $application->ulid,
                    'document_type_id' => $requirement->document_type_id,
                    'status' => DocumentStatus::Pending,
                ]);
            }

            return $application;
        });
    }
}
