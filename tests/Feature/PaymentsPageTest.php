<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Domain\Payments\Models\Invoice;
use App\Domain\Payments\Models\Payment;
use App\Livewire\Payments\PaymentsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PaymentsPageTest extends TestCase
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
        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->user->assignRole('applicant');
        $this->profile = ApplicantProfile::factory()->create([
            'user_id' => $this->user->id,
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
        ]);
    }

    public function test_payments_page_requires_authentication(): void
    {
        $this->get(route('payments'))->assertRedirect(route('login'));
    }

    public function test_authenticated_applicant_can_access_payments_page(): void
    {
        $this->actingAs($this->user)->get(route('payments'))->assertOk();
    }

    public function test_empty_state_shown_when_no_payments(): void
    {
        Livewire::actingAs($this->user)
            ->test(PaymentsPage::class)
            ->assertSee('No payments yet');
    }

    public function test_pending_alert_shown_when_application_awaits_payment(): void
    {
        VisaApplication::factory()->create([
            'applicant_profile_id' => $this->profile->ulid,
            'status' => ApplicationStatus::PaymentPending,
        ]);

        $component = Livewire::actingAs($this->user)->test(PaymentsPage::class);
        $this->assertGreaterThan(0, $component->get('pendingCount'));
    }

    public function test_no_pending_alert_when_all_payments_complete(): void
    {
        VisaApplication::factory()->create([
            'applicant_profile_id' => $this->profile->ulid,
            'status' => ApplicationStatus::Approved,
        ]);

        $component = Livewire::actingAs($this->user)->test(PaymentsPage::class);
        $this->assertEquals(0, $component->get('pendingCount'));
    }

    public function test_payment_history_shows_succeeded_payment(): void
    {
        $application = VisaApplication::factory()->create([
            'applicant_profile_id' => $this->profile->ulid,
            'status' => ApplicationStatus::PaymentCompleted,
        ]);

        Payment::factory()->succeeded()->create([
            'visa_application_id' => $application->ulid,
        ]);

        Livewire::actingAs($this->user)
            ->test(PaymentsPage::class)
            ->assertSee('Succeeded');
    }

    public function test_receipt_download_link_shown_when_invoice_exists(): void
    {
        $application = VisaApplication::factory()->create([
            'applicant_profile_id' => $this->profile->ulid,
            'status' => ApplicationStatus::PaymentCompleted,
        ]);

        $payment = Payment::factory()->succeeded()->create([
            'visa_application_id' => $application->ulid,
        ]);

        Invoice::factory()->create([
            'payment_id' => $payment->ulid,
            'pdf_storage_path' => 'receipts/test.pdf',
        ]);

        Livewire::actingAs($this->user)
            ->test(PaymentsPage::class)
            ->assertSee('Receipt');
    }

    public function test_pay_now_link_shown_for_pending_payment_application(): void
    {
        $application = VisaApplication::factory()->create([
            'applicant_profile_id' => $this->profile->ulid,
            'status' => ApplicationStatus::PaymentPending,
        ]);

        Payment::factory()->processing()->create([
            'visa_application_id' => $application->ulid,
        ]);

        Livewire::actingAs($this->user)
            ->test(PaymentsPage::class)
            ->assertSee('Pay Now');
    }

    public function test_applicant_cannot_see_other_users_payments(): void
    {
        $country = Country::factory()->create();
        $otherUser = User::factory()->create();
        $otherUser->assignRole('applicant');
        $otherProfile = ApplicantProfile::factory()->create([
            'user_id' => $otherUser->id,
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
        ]);
        $otherApp = VisaApplication::factory()->create([
            'applicant_profile_id' => $otherProfile->ulid,
            'status' => ApplicationStatus::PaymentCompleted,
        ]);
        $otherPayment = Payment::factory()->succeeded()->create([
            'visa_application_id' => $otherApp->ulid,
        ]);

        Livewire::actingAs($this->user)
            ->test(PaymentsPage::class)
            ->assertDontSee($otherPayment->ulid);
    }
}
