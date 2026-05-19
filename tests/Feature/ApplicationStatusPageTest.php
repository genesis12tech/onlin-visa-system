<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationAppointment;
use App\Domain\Applications\Models\ApplicationNote;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Domain\Payments\Models\Invoice;
use App\Domain\Payments\Models\Payment;
use App\Livewire\Applications\ApplicationStatusPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApplicationStatusPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ApplicantProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);

        $country = Country::factory()->create();
        $this->user = User::factory()->create();
        $this->user->assignRole('applicant');
        $this->profile = ApplicantProfile::factory()->create([
            'user_id' => $this->user->id,
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
        ]);
    }

    public function test_renders_status_label(): void
    {
        $application = $this->makeApplication(ApplicationStatus::Submitted);

        Livewire::actingAs($this->user)
            ->test(ApplicationStatusPage::class, ['applicationUlid' => $application->ulid])
            ->assertSee('Submitted');
    }

    public function test_renders_status_description(): void
    {
        $application = $this->makeApplication(ApplicationStatus::UnderReview);

        Livewire::actingAs($this->user)
            ->test(ApplicationStatusPage::class, ['applicationUlid' => $application->ulid])
            ->assertSee('An officer is currently reviewing your application.');
    }

    public function test_renders_tracking_number(): void
    {
        $application = $this->makeApplication(ApplicationStatus::Submitted);

        Livewire::actingAs($this->user)
            ->test(ApplicationStatusPage::class, ['applicationUlid' => $application->ulid])
            ->assertSee($application->tracking_number);
    }

    public function test_renders_decision_details_when_approved(): void
    {
        $application = $this->makeApplication(ApplicationStatus::Approved, [
            'decision_reason' => 'All requirements met.',
            'decision_at' => now(),
            'validity_period' => '6 months',
            'entry_type' => 'single',
        ]);

        Livewire::actingAs($this->user)
            ->test(ApplicationStatusPage::class, ['applicationUlid' => $application->ulid])
            ->assertSee('All requirements met.')
            ->assertSee('6 months')
            ->assertSee('single');
    }

    public function test_renders_decision_reason_when_rejected(): void
    {
        $application = $this->makeApplication(ApplicationStatus::Rejected, [
            'decision_reason' => 'Insufficient documentation.',
            'decision_at' => now(),
        ]);

        Livewire::actingAs($this->user)
            ->test(ApplicationStatusPage::class, ['applicationUlid' => $application->ulid])
            ->assertSee('Insufficient documentation.');
    }

    public function test_decision_section_hidden_when_not_decided(): void
    {
        $application = $this->makeApplication(ApplicationStatus::UnderReview);

        Livewire::actingAs($this->user)
            ->test(ApplicationStatusPage::class, ['applicationUlid' => $application->ulid])
            ->assertDontSee('Decision details');
    }

    public function test_renders_appointment_when_present(): void
    {
        $application = $this->makeApplication(ApplicationStatus::UnderReview);

        ApplicationAppointment::create([
            'visa_application_id' => $application->ulid,
            'created_by' => $this->user->id,
            'appointment_at' => now()->addDays(7),
            'location' => 'Visa Center, Main Street',
            'instructions' => 'Bring your original passport.',
        ]);

        Livewire::actingAs($this->user)
            ->test(ApplicationStatusPage::class, ['applicationUlid' => $application->ulid])
            ->assertSee('Visa Center, Main Street')
            ->assertSee('Bring your original passport.');
    }

    public function test_appointment_section_hidden_when_no_appointment(): void
    {
        $application = $this->makeApplication(ApplicationStatus::UnderReview);

        Livewire::actingAs($this->user)
            ->test(ApplicationStatusPage::class, ['applicationUlid' => $application->ulid])
            ->assertDontSee('Appointment');
    }

    public function test_shows_notes_visible_to_applicant(): void
    {
        $application = $this->makeApplication(ApplicationStatus::UnderReview);
        $officer = User::factory()->create();

        ApplicationNote::factory()->create([
            'visa_application_id' => $application->ulid,
            'author_id' => $officer->id,
            'body' => 'Your documents look great.',
            'is_visible_to_applicant' => true,
        ]);

        Livewire::actingAs($this->user)
            ->test(ApplicationStatusPage::class, ['applicationUlid' => $application->ulid])
            ->assertSee('Your documents look great.');
    }

    public function test_hides_notes_not_visible_to_applicant(): void
    {
        $application = $this->makeApplication(ApplicationStatus::UnderReview);
        $officer = User::factory()->create();

        ApplicationNote::factory()->create([
            'visa_application_id' => $application->ulid,
            'author_id' => $officer->id,
            'body' => 'Internal: suspect fraud.',
            'is_visible_to_applicant' => false,
        ]);

        Livewire::actingAs($this->user)
            ->test(ApplicationStatusPage::class, ['applicationUlid' => $application->ulid])
            ->assertDontSee('Internal: suspect fraud.');
    }

    public function test_renders_status_history_section(): void
    {
        $application = $this->makeApplication(ApplicationStatus::UnderReview);

        ApplicationStatusHistory::create([
            'visa_application_id' => $application->ulid,
            'from_status' => ApplicationStatus::Draft->value,
            'to_status' => ApplicationStatus::Submitted->value,
            'actor_id' => $this->user->id,
            'created_at' => now(),
        ]);

        Livewire::actingAs($this->user)
            ->test(ApplicationStatusPage::class, ['applicationUlid' => $application->ulid])
            ->assertSee('Application history');
    }

    public function test_renders_payment_receipt_link_when_invoice_pdf_available(): void
    {
        $application = $this->makeApplication(ApplicationStatus::UnderReview);

        $payment = Payment::factory()->succeeded()->create([
            'visa_application_id' => $application->ulid,
        ]);

        Invoice::factory()->create([
            'payment_id' => $payment->ulid,
            'pdf_storage_path' => 'invoices/receipt.pdf',
        ]);

        Livewire::actingAs($this->user)
            ->test(ApplicationStatusPage::class, ['applicationUlid' => $application->ulid])
            ->assertSee('Download Receipt');
    }

    public function test_receipt_link_hidden_when_no_payment(): void
    {
        $application = $this->makeApplication(ApplicationStatus::Submitted);

        Livewire::actingAs($this->user)
            ->test(ApplicationStatusPage::class, ['applicationUlid' => $application->ulid])
            ->assertDontSee('Download Receipt');
    }

    /** @param array<string, mixed> $overrides */
    private function makeApplication(ApplicationStatus $status, array $overrides = []): VisaApplication
    {
        return VisaApplication::factory()->create(array_merge([
            'applicant_profile_id' => $this->profile->ulid,
            'status' => $status,
            'submitted_at' => now(),
        ], $overrides));
    }
}
