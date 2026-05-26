<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Domain\Payments\Actions\ConfirmPayment;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Jobs\GenerateReceiptPdf;
use App\Domain\Payments\Models\Invoice;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentSucceededNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ConfirmPaymentTest extends TestCase
{
    use RefreshDatabase;

    private VisaApplication $application;

    private Payment $payment;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();

        $country = Country::factory()->create();
        $visaType = VisaType::factory()->create();
        $template = FormTemplate::factory()->for($visaType)->create();
        $applicant = User::factory()->create();
        $profile = ApplicantProfile::factory()->for($applicant, 'user')->for($country, 'nationality')->create();

        $this->application = VisaApplication::factory()
            ->for($profile, 'applicantProfile')
            ->for($visaType)
            ->for($template, 'formTemplate')
            ->create(['status' => ApplicationStatus::PaymentPending]);

        $this->payment = Payment::factory()->create([
            'visa_application_id' => $this->application->ulid,
            'status' => PaymentStatus::Processing,
        ]);
    }

    public function test_marks_payment_succeeded(): void
    {
        Queue::fake();

        (new ConfirmPayment)->execute($this->payment, $this->admin);

        $this->assertEquals(PaymentStatus::Succeeded, $this->payment->fresh()->status);
        $this->assertNotNull($this->payment->fresh()->succeeded_at);
    }

    public function test_transitions_application_to_payment_completed(): void
    {
        Queue::fake();

        (new ConfirmPayment)->execute($this->payment, $this->admin);

        $this->assertEquals(ApplicationStatus::PaymentCompleted, $this->application->fresh()->status);
    }

    public function test_writes_status_history_with_actor(): void
    {
        Queue::fake();

        (new ConfirmPayment)->execute($this->payment, $this->admin);

        $this->assertDatabaseHas('application_status_histories', [
            'visa_application_id' => $this->application->ulid,
            'from_status' => 'payment_pending',
            'to_status' => 'payment_completed',
            'actor_id' => $this->admin->id,
        ]);
    }

    public function test_creates_invoice(): void
    {
        Queue::fake();

        (new ConfirmPayment)->execute($this->payment, $this->admin);

        $this->assertDatabaseHas('invoices', ['payment_id' => $this->payment->ulid]);
    }

    public function test_dispatches_generate_receipt_pdf_job(): void
    {
        Queue::fake();

        (new ConfirmPayment)->execute($this->payment, $this->admin);

        Queue::assertPushedOn('pdfs', GenerateReceiptPdf::class);
    }

    public function test_sends_payment_succeeded_notification(): void
    {
        Notification::fake();
        Queue::fake();

        (new ConfirmPayment)->execute($this->payment, $this->admin);

        $applicantUser = $this->application->applicantProfile->user;
        Notification::assertSentTo($applicantUser, PaymentSucceededNotification::class);
    }

    public function test_does_nothing_if_already_succeeded(): void
    {
        Queue::fake();

        $this->payment->update(['status' => PaymentStatus::Succeeded, 'succeeded_at' => now()]);
        Invoice::factory()->for($this->payment, 'payment')->create();

        (new ConfirmPayment)->execute($this->payment, $this->admin);

        Queue::assertNotPushed(GenerateReceiptPdf::class);
        $this->assertEquals(1, Invoice::where('payment_id', $this->payment->ulid)->count());
    }

    public function test_does_not_double_confirm_when_db_already_succeeded(): void
    {
        Queue::fake();

        // Update DB directly, bypassing Eloquent events — in-memory model stays stale (Processing)
        Payment::where('ulid', $this->payment->ulid)->update([
            'status' => PaymentStatus::Succeeded->value,
            'succeeded_at' => now(),
        ]);
        Invoice::factory()->for($this->payment, 'payment')->create();

        $this->assertEquals(PaymentStatus::Processing, $this->payment->status);

        (new ConfirmPayment)->execute($this->payment, $this->admin);

        Queue::assertNotPushed(GenerateReceiptPdf::class);
        $this->assertSame(1, Invoice::where('payment_id', $this->payment->ulid)->count());
    }
}
