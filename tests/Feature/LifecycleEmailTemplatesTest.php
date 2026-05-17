<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationAppointment;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Domain\Payments\Models\Invoice;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use App\Notifications\AdditionalInfoRequestedNotification;
use App\Notifications\ApplicationApprovedNotification;
use App\Notifications\ApplicationRejectedNotification;
use App\Notifications\ApplicationSubmittedNotification;
use App\Notifications\AppointmentScheduledNotification;
use App\Notifications\DocumentRejectedNotification;
use App\Notifications\PaymentSucceededNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class LifecycleEmailTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_submitted_email_uses_custom_template(): void
    {
        ['application' => $application, 'user' => $user] = $this->makeApplicationContext();

        $mail = (new ApplicationSubmittedNotification($application))->toMail($user);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertEquals('Your visa application has been received', $mail->subject);
        $this->assertEquals('emails.application-submitted', $mail->markdown);
        $this->assertEquals($application->tracking_number, $mail->viewData['trackingNumber']);
        $this->assertEquals(route('dashboard'), $mail->viewData['dashboardUrl']);
    }

    public function test_application_approved_email_uses_custom_template(): void
    {
        ['application' => $application, 'user' => $user] = $this->makeApplicationContext();

        $mail = (new ApplicationApprovedNotification($application))->toMail($user);

        $this->assertEquals('Your visa application has been approved', $mail->subject);
        $this->assertEquals('emails.application-approved', $mail->markdown);
        $this->assertEquals($application->tracking_number, $mail->viewData['trackingNumber']);
        $this->assertEquals(route('dashboard'), $mail->viewData['dashboardUrl']);
    }

    public function test_application_rejected_email_uses_custom_template(): void
    {
        ['application' => $application, 'user' => $user] = $this->makeApplicationContext();

        $mail = (new ApplicationRejectedNotification($application))->toMail($user);

        $this->assertEquals('Your visa application decision', $mail->subject);
        $this->assertEquals('emails.application-rejected', $mail->markdown);
        $this->assertEquals($application->tracking_number, $mail->viewData['trackingNumber']);
    }

    public function test_additional_info_requested_email_passes_officer_message(): void
    {
        ['application' => $application, 'user' => $user] = $this->makeApplicationContext();

        $mail = (new AdditionalInfoRequestedNotification($application, 'Please provide your employment letter.'))->toMail($user);

        $this->assertEquals('Additional information requested for your visa application', $mail->subject);
        $this->assertEquals('emails.additional-info-requested', $mail->markdown);
        $this->assertEquals('Please provide your employment letter.', $mail->viewData['officerMessage']);
    }

    public function test_document_rejected_email_passes_reason_and_tracking_number(): void
    {
        ['application' => $application, 'user' => $user] = $this->makeApplicationContext();

        $docType = DocumentType::factory()->create(['name' => 'Passport']);
        $doc = ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => 'rejected',
        ]);

        $mail = (new DocumentRejectedNotification($doc, 'Image is blurry.'))->toMail($user);

        $this->assertEquals('Document rejected — action required', $mail->subject);
        $this->assertEquals('emails.document-rejected', $mail->markdown);
        $this->assertEquals('Image is blurry.', $mail->viewData['rejectionReason']);
        $this->assertEquals($application->tracking_number, $mail->viewData['trackingNumber']);
    }

    public function test_document_rejected_toarray_includes_tracking_number(): void
    {
        ['application' => $application, 'user' => $user] = $this->makeApplicationContext();

        $docType = DocumentType::factory()->create(['name' => 'Photo']);
        $doc = ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => 'rejected',
        ]);

        $data = (new DocumentRejectedNotification($doc, 'Too small.'))->toArray($user);

        $this->assertEquals($application->tracking_number, $data['tracking_number']);
    }

    public function test_payment_succeeded_email_passes_invoice_number_and_receipt_url(): void
    {
        ['application' => $application, 'user' => $user] = $this->makeApplicationContext();

        $payment = Payment::factory()->create([
            'visa_application_id' => $application->ulid,
        ]);
        $invoice = Invoice::factory()->create([
            'payment_id' => $payment->ulid,
            'invoice_number' => 'INV-0001',
        ]);

        $mail = (new PaymentSucceededNotification($payment, $invoice))->toMail($user);

        $this->assertEquals('Payment received — Invoice #INV-0001', $mail->subject);
        $this->assertEquals('emails.payment-succeeded', $mail->markdown);
        $this->assertEquals('INV-0001', $mail->viewData['invoiceNumber']);
        $this->assertEquals(route('invoices.receipt', $invoice->ulid), $mail->viewData['receiptUrl']);
    }

    public function test_appointment_scheduled_email_passes_location_and_application_url(): void
    {
        ['application' => $application, 'user' => $user] = $this->makeApplicationContext();

        $appointment = ApplicationAppointment::create([
            'visa_application_id' => $application->ulid,
            'created_by' => $user->id,
            'appointment_at' => now()->addDays(7),
            'location' => 'Visa Centre, London',
            'instructions' => 'Bring originals.',
        ]);

        $mail = (new AppointmentScheduledNotification($application, $appointment))->toMail($user);

        $this->assertEquals('Appointment scheduled for your visa application', $mail->subject);
        $this->assertEquals('emails.appointment-scheduled', $mail->markdown);
        $this->assertEquals('Visa Centre, London', $mail->viewData['location']);
        $this->assertEquals(route('applications.wizard', $application->tracking_number), $mail->viewData['applicationUrl']);
    }

    private function makeApplicationContext(): array
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'TE', 'iso3' => 'TST']);
        $type = VisaType::create(['name' => 'Tourist', 'code' => 'TOURIST_30', 'country_id' => $country->id, 'processing_days' => 3, 'validity_days' => 30]);
        $form = FormTemplate::create(['visa_type_id' => $type->ulid, 'name' => 'Tourist Form', 'schema' => json_encode([])]);
        $user = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'Applicant',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
            'passport_number' => 'A12345678',
            'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890',
            'address_line_1' => '1 Test Street',
            'city' => 'London',
        ]);
        $application = VisaApplication::create([
            'tracking_number' => 'VA-TMPL-001',
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $type->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::Draft,
        ]);

        return compact('application', 'user');
    }
}
