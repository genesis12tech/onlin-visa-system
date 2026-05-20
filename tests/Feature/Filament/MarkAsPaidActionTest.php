<?php

namespace Tests\Feature\Filament;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Jobs\GenerateReceiptPdf;
use App\Domain\Payments\Models\Payment;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MarkAsPaidActionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Payment $payment;

    private VisaApplication $application;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');

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

    public function test_mark_as_paid_action_is_visible_for_non_succeeded_payment(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ViewPayment::class, ['record' => $this->payment->ulid])
            ->assertActionVisible('markAsPaid');
    }

    public function test_mark_as_paid_action_is_hidden_for_succeeded_payment(): void
    {
        $this->actingAs($this->admin);

        $this->payment->update(['status' => PaymentStatus::Succeeded, 'succeeded_at' => now()]);

        Livewire::test(ViewPayment::class, ['record' => $this->payment->ulid])
            ->assertActionHidden('markAsPaid');
    }

    public function test_mark_as_paid_confirms_payment_and_shows_notification(): void
    {
        Queue::fake();
        $this->actingAs($this->admin);

        Livewire::test(ViewPayment::class, ['record' => $this->payment->ulid])
            ->callAction('markAsPaid')
            ->assertNotified();

        $this->assertEquals(PaymentStatus::Succeeded, $this->payment->fresh()->status);
        $this->assertEquals(ApplicationStatus::PaymentCompleted, $this->application->fresh()->status);
        Queue::assertPushedOn('pdfs', GenerateReceiptPdf::class);
    }
}
