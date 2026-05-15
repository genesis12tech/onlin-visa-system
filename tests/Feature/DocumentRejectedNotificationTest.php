<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Documents\Actions\RejectDocument;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use App\Notifications\DocumentRejectedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DocumentRejectedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reject_document_dispatches_notification_to_applicant(): void
    {
        Notification::fake();

        ['document' => $document, 'applicantUser' => $applicantUser, 'officer' => $officer] = $this->makeContext();

        (new RejectDocument)->execute($document, $officer, 'Photo too dark.');

        Notification::assertSentTo($applicantUser, DocumentRejectedNotification::class);
    }

    public function test_rejected_document_notification_contains_rejection_reason(): void
    {
        Notification::fake();

        ['document' => $document, 'applicantUser' => $applicantUser, 'officer' => $officer] = $this->makeContext();

        (new RejectDocument)->execute($document, $officer, 'Blurry image.');

        Notification::assertSentTo(
            $applicantUser,
            DocumentRejectedNotification::class,
            function (DocumentRejectedNotification $n) use ($applicantUser) {
                $data = $n->toArray($applicantUser);

                return $data['type'] === 'document_rejected'
                    && $data['reason'] === 'Blurry image.';
            }
        );
    }

    public function test_document_rejected_notification_is_on_emails_queue(): void
    {
        ['document' => $document] = $this->makeContext();

        $notification = new DocumentRejectedNotification($document, 'Bad photo.');

        $this->assertEquals('emails', $notification->queue);
    }

    private function makeContext(): array
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'TE', 'iso3' => 'TST']);
        $type = VisaType::create(['name' => 'Tourist', 'code' => 'TOURIST_DR', 'country_id' => $country->id, 'processing_days' => 3, 'validity_days' => 30]);
        $form = FormTemplate::create(['visa_type_id' => $type->ulid, 'name' => 'Tourist Form', 'schema' => json_encode([])]);
        $applicantUser = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $applicantUser->id,
            'first_name' => 'Test', 'last_name' => 'Applicant',
            'date_of_birth' => '1990-01-01', 'gender' => 'male',
            'nationality_id' => $country->id, 'country_of_residence_id' => $country->id,
            'passport_number' => 'A12345678', 'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890', 'address_line_1' => '1 Test Street', 'city' => 'London',
        ]);
        $application = VisaApplication::create([
            'tracking_number' => 'VA-DR-001',
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $type->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::Submitted,
        ]);
        $docType = DocumentType::factory()->create();
        $document = ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Uploaded,
        ]);
        $officer = User::factory()->create();

        return compact('document', 'applicantUser', 'officer');
    }
}
