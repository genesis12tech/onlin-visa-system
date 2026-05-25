<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Livewire\ApplicantDashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApplicantDashboardTest extends TestCase
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

    public function test_renders_dashboard_for_authenticated_verified_applicant(): void
    {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_unverified_user_is_redirected_to_email_verification(): void
    {
        $unverified = User::factory()->create(['email_verified_at' => null]);
        $unverified->assignRole('applicant');

        $this->actingAs($unverified)
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_applicant_only_sees_their_own_applications(): void
    {
        $country = Country::factory()->create();
        $otherUser = User::factory()->create(['email_verified_at' => now()]);
        $otherUser->assignRole('applicant');
        $otherProfile = ApplicantProfile::factory()->create([
            'user_id' => $otherUser->id,
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
        ]);

        $appA = $this->makeApplication(ApplicationStatus::Submitted);
        $appB = VisaApplication::factory()->submitted()->create([
            'applicant_profile_id' => $otherProfile->ulid,
        ]);

        Livewire::actingAs($this->user)
            ->test(ApplicantDashboard::class)
            ->assertSee($appA->tracking_number)
            ->assertDontSee($appB->tracking_number);
    }

    public function test_alert_bar_is_visible_when_info_requested_application_exists(): void
    {
        $this->makeApplication(ApplicationStatus::AdditionalInfoRequested);

        Livewire::actingAs($this->user)
            ->test(ApplicantDashboard::class)
            ->assertSet('actionRequiredApp', fn ($v) => $v !== null);
    }

    public function test_alert_bar_is_hidden_when_no_info_requested_applications(): void
    {
        Livewire::actingAs($this->user)
            ->test(ApplicantDashboard::class)
            ->assertSet('actionRequiredApp', null);
    }

    public function test_quick_actions_includes_resubmit_only_when_info_requested_exists(): void
    {
        $this->makeApplication(ApplicationStatus::AdditionalInfoRequested);

        $component = Livewire::actingAs($this->user)->test(ApplicantDashboard::class);
        $labels = collect($component->get('quickActions'))->pluck('label')->all();

        $this->assertContains('Resubmit documents', $labels);
    }

    public function test_resubmit_not_in_quick_actions_when_no_info_requested(): void
    {
        $this->makeApplication(ApplicationStatus::UnderReview);

        $component = Livewire::actingAs($this->user)->test(ApplicantDashboard::class);
        $labels = collect($component->get('quickActions'))->pluck('label')->all();

        $this->assertNotContains('Resubmit documents', $labels);
    }

    public function test_quick_actions_includes_download_only_when_approved_exists(): void
    {
        $this->makeApplication(ApplicationStatus::Approved);

        $component = Livewire::actingAs($this->user)->test(ApplicantDashboard::class);
        $labels = collect($component->get('quickActions'))->pluck('label')->all();

        $this->assertContains('Download decision letter', $labels);
    }

    public function test_stat_counts_are_correct(): void
    {
        $this->makeApplication(ApplicationStatus::Approved);
        $this->makeApplication(ApplicationStatus::UnderReview);

        Livewire::actingAs($this->user)
            ->test(ApplicantDashboard::class)
            ->assertSet('totalCount', 2)
            ->assertSet('approvedCount', 1)
            ->assertSet('inProgressCount', 1);
    }

    public function test_action_needed_count_reflects_info_requested_applications(): void
    {
        $this->makeApplication(ApplicationStatus::AdditionalInfoRequested);
        $this->makeApplication(ApplicationStatus::Submitted);

        Livewire::actingAs($this->user)
            ->test(ApplicantDashboard::class)
            ->assertSet('actionNeededCount', 1)
            ->assertSet('totalCount', 2);
    }

    public function test_empty_state_shown_when_no_applications(): void
    {
        Livewire::actingAs($this->user)
            ->test(ApplicantDashboard::class)
            ->assertSee('No applications yet');
    }

    public function test_track_an_application_always_in_quick_actions(): void
    {
        $component = Livewire::actingAs($this->user)->test(ApplicantDashboard::class);
        $labels = collect($component->get('quickActions'))->pluck('label')->all();

        $this->assertContains('Track an application', $labels);
    }

    public function test_nav_contains_documents_link(): void
    {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('documents'));
    }

    public function test_alert_bar_is_visible_when_payment_pending_application_exists(): void
    {
        $this->makeApplication(ApplicationStatus::PaymentPending);

        Livewire::actingAs($this->user)
            ->test(ApplicantDashboard::class)
            ->assertSet('actionRequiredApp', fn ($v) => $v !== null);
    }

    public function test_alert_bar_is_visible_when_approved_application_has_decision_letter(): void
    {
        $this->makeApplication(ApplicationStatus::Approved, [
            'decision_letter_pdf_path' => 'letters/decision.pdf',
        ]);

        Livewire::actingAs($this->user)
            ->test(ApplicantDashboard::class)
            ->assertSet('actionRequiredApp', fn ($v) => $v !== null);
    }

    public function test_alert_bar_hidden_when_approved_application_has_no_decision_letter(): void
    {
        $this->makeApplication(ApplicationStatus::Approved, [
            'decision_letter_pdf_path' => null,
        ]);

        Livewire::actingAs($this->user)
            ->test(ApplicantDashboard::class)
            ->assertSet('actionRequiredApp', null);
    }

    public function test_action_needed_count_includes_payment_pending(): void
    {
        $this->makeApplication(ApplicationStatus::AdditionalInfoRequested);
        $this->makeApplication(ApplicationStatus::PaymentPending);
        $this->makeApplication(ApplicationStatus::UnderReview);

        Livewire::actingAs($this->user)
            ->test(ApplicantDashboard::class)
            ->assertSet('actionNeededCount', 2);
    }

    public function test_quick_actions_includes_complete_payment_when_payment_pending_exists(): void
    {
        $this->makeApplication(ApplicationStatus::PaymentPending);

        $component = Livewire::actingAs($this->user)->test(ApplicantDashboard::class);
        $labels = collect($component->get('quickActions'))->pluck('label')->all();

        $this->assertContains('Complete payment', $labels);
    }

    public function test_complete_payment_not_in_quick_actions_when_no_payment_pending(): void
    {
        $this->makeApplication(ApplicationStatus::Submitted);

        $component = Livewire::actingAs($this->user)->test(ApplicantDashboard::class);
        $labels = collect($component->get('quickActions'))->pluck('label')->all();

        $this->assertNotContains('Complete payment', $labels);
    }

    public function test_accepted_doc_count_is_alias_for_accepted_documents_count(): void
    {
        $app = $this->makeApplication(ApplicationStatus::Submitted);

        $this->assertEquals($app->acceptedDocumentsCount(), $app->acceptedDocCount());
    }

    public function test_formatted_fee_returns_dash_when_no_active_fee(): void
    {
        $app = $this->makeApplication(ApplicationStatus::Submitted);

        $this->assertEquals('—', $app->formattedFee());
    }

    public function test_dashboard_shows_at_most_three_recent_applications(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeApplication(ApplicationStatus::Submitted);
        }

        $component = Livewire::actingAs($this->user)->test(ApplicantDashboard::class);

        $this->assertCount(3, $component->get('applications'));
    }

    public function test_stats_array_has_correct_counts(): void
    {
        $this->makeApplication(ApplicationStatus::Approved);
        $this->makeApplication(ApplicationStatus::UnderReview);
        $this->makeApplication(ApplicationStatus::PaymentPending);

        $component = Livewire::actingAs($this->user)->test(ApplicantDashboard::class);
        $stats = $component->get('stats');

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(1, $stats['approved']);
        $this->assertEquals(1, $stats['underReview']);
        $this->assertEquals(1, $stats['pendingPayment']);
    }

    public function test_upcoming_trip_is_null_when_no_approved_apps_with_future_travel(): void
    {
        $this->makeApplication(ApplicationStatus::Approved); // travel_date = null

        Livewire::actingAs($this->user)
            ->test(ApplicantDashboard::class)
            ->assertSet('upcomingTrip', null);
    }

    public function test_upcoming_trip_is_set_when_approved_app_has_future_travel_date(): void
    {
        $app = $this->makeApplication(ApplicationStatus::Approved, [
            'travel_date' => now()->addDays(14)->toDateString(),
        ]);

        Livewire::actingAs($this->user)
            ->test(ApplicantDashboard::class)
            ->assertSet('upcomingTrip', fn ($v) => $v !== null && $v->ulid === $app->ulid);
    }

    public function test_upcoming_trip_is_earliest_future_approved_travel(): void
    {
        $later = $this->makeApplication(ApplicationStatus::Approved, [
            'travel_date' => now()->addDays(30)->toDateString(),
        ]);
        $sooner = $this->makeApplication(ApplicationStatus::Approved, [
            'travel_date' => now()->addDays(10)->toDateString(),
        ]);

        Livewire::actingAs($this->user)
            ->test(ApplicantDashboard::class)
            ->assertSet('upcomingTrip', fn ($v) => $v !== null && $v->ulid === $sooner->ulid);
    }

    public function test_welcome_banner_shows_user_first_name(): void
    {
        $this->user->update(['name' => 'Maria Santos']);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertSee('Maria');
    }

    public function test_new_application_cta_link_is_present(): void
    {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertSee(route('applications.start'));
    }

    /** @param array<string, mixed> $overrides */
    private function makeApplication(ApplicationStatus $status, array $overrides = []): VisaApplication
    {
        return VisaApplication::factory()->create(array_merge([
            'applicant_profile_id' => $this->profile->ulid,
            'status' => $status,
            'submitted_at' => in_array($status, [ApplicationStatus::Draft], true) ? null : now(),
        ], $overrides));
    }
}
