<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Payments\Models\Invoice;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ApplicantProfile $profile;

    private VisaApplication $application;

    private Payment $payment;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);

        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->user->assignRole('applicant');

        $this->profile = ApplicantProfile::factory()->create(['user_id' => $this->user->id]);

        $this->application = VisaApplication::factory()->create([
            'applicant_profile_id' => $this->profile->ulid,
            'status' => ApplicationStatus::PaymentCompleted,
        ]);

        $this->payment = Payment::factory()->succeeded()->create([
            'visa_application_id' => $this->application->ulid,
            'provider_checkout_session_id' => 'cs_test_success123',
        ]);

        $this->invoice = Invoice::factory()->create(['payment_id' => $this->payment->ulid]);
    }

    public function test_success_page_shows_payment_details(): void
    {
        $this->actingAs($this->user)
            ->get(route('payment.success', ['session_id' => 'cs_test_success123']))
            ->assertOk()
            ->assertViewHas('payment', fn ($p) => $p->ulid === $this->payment->ulid)
            ->assertViewHas('invoice', fn ($i) => $i->ulid === $this->invoice->ulid)
            ->assertViewHas('application', fn ($a) => $a->ulid === $this->application->ulid);
    }

    public function test_success_page_redirects_to_dashboard_when_session_id_missing(): void
    {
        $this->actingAs($this->user)
            ->get(route('payment.success'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_success_page_redirects_to_dashboard_when_session_id_not_found(): void
    {
        $this->actingAs($this->user)
            ->get(route('payment.success', ['session_id' => 'cs_test_does_not_exist']))
            ->assertRedirect(route('dashboard'));
    }

    public function test_success_page_is_not_accessible_to_other_applicants(): void
    {
        $otherUser = User::factory()->create(['email_verified_at' => now()]);
        $otherUser->assignRole('applicant');
        ApplicantProfile::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($otherUser)
            ->get(route('payment.success', ['session_id' => 'cs_test_success123']))
            ->assertForbidden();
    }

    public function test_success_page_requires_authentication(): void
    {
        $this->get(route('payment.success', ['session_id' => 'cs_test_success123']))
            ->assertRedirect(route('login'));
    }
}
