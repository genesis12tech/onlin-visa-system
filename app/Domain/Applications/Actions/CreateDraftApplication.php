<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
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
            return VisaApplication::create([
                'tracking_number' => $this->generateTrackingNumber->execute(),
                'applicant_profile_id' => $applicantProfile->ulid,
                'visa_type_id' => $visaType->ulid,
                'form_template_id' => $formTemplate->ulid,
                'status' => ApplicationStatus::Draft,
                'travel_date' => $travelDate,
            ]);
        });
    }
}
