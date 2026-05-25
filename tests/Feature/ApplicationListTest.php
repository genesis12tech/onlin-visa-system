<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Livewire\ApplicationDetail;
use App\Livewire\ApplicationList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApplicationListTest extends TestCase
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

    // -----------------------------------------------------------------------
    // ApplicationList — route
    // -----------------------------------------------------------------------

    public function test_applications_index_route_renders_application_list(): void
    {
        $this->actingAs($this->user)->get('/applications')->assertOk();
    }

    // -----------------------------------------------------------------------
    // ApplicationList — default filter
    // -----------------------------------------------------------------------

    public function test_default_filter_is_all_and_shows_all_statuses(): void
    {
        $approved = $this->makeApplication(ApplicationStatus::Approved);
        $rejected = $this->makeApplication(ApplicationStatus::Rejected);

        Livewire::actingAs($this->user)
            ->test(ApplicationList::class)
            ->assertSet('filter', 'all')
            ->assertSee($approved->tracking_number)
            ->assertSee($rejected->tracking_number);
    }

    // -----------------------------------------------------------------------
    // ApplicationList — filter tabs
    // -----------------------------------------------------------------------

    public function test_filter_approved_shows_only_approved_applications(): void
    {
        $approved = $this->makeApplication(ApplicationStatus::Approved);
        $submitted = $this->makeApplication(ApplicationStatus::Submitted);

        Livewire::actingAs($this->user)
            ->test(ApplicationList::class, ['filter' => 'approved'])
            ->assertSee($approved->tracking_number)
            ->assertDontSee($submitted->tracking_number);
    }

    public function test_filter_in_review_shows_under_review_applications(): void
    {
        $underReview = $this->makeApplication(ApplicationStatus::UnderReview);
        $submitted = $this->makeApplication(ApplicationStatus::Submitted);

        Livewire::actingAs($this->user)
            ->test(ApplicationList::class, ['filter' => 'in_review'])
            ->assertSee($underReview->tracking_number)
            ->assertDontSee($submitted->tracking_number);
    }

    public function test_filter_in_review_also_shows_additional_info_requested(): void
    {
        $infoRequested = $this->makeApplication(ApplicationStatus::AdditionalInfoRequested);
        $approved = $this->makeApplication(ApplicationStatus::Approved);

        Livewire::actingAs($this->user)
            ->test(ApplicationList::class, ['filter' => 'in_review'])
            ->assertSee($infoRequested->tracking_number)
            ->assertDontSee($approved->tracking_number);
    }

    public function test_filter_submitted_shows_submitted_applications(): void
    {
        $submitted = $this->makeApplication(ApplicationStatus::Submitted);
        $approved = $this->makeApplication(ApplicationStatus::Approved);

        Livewire::actingAs($this->user)
            ->test(ApplicationList::class, ['filter' => 'submitted'])
            ->assertSee($submitted->tracking_number)
            ->assertDontSee($approved->tracking_number);
    }

    public function test_filter_submitted_also_shows_payment_pending(): void
    {
        $paymentPending = $this->makeApplication(ApplicationStatus::PaymentPending);
        $rejected = $this->makeApplication(ApplicationStatus::Rejected);

        Livewire::actingAs($this->user)
            ->test(ApplicationList::class, ['filter' => 'submitted'])
            ->assertSee($paymentPending->tracking_number)
            ->assertDontSee($rejected->tracking_number);
    }

    public function test_filter_rejected_shows_only_rejected_applications(): void
    {
        $rejected = $this->makeApplication(ApplicationStatus::Rejected);
        $submitted = $this->makeApplication(ApplicationStatus::Submitted);

        Livewire::actingAs($this->user)
            ->test(ApplicationList::class, ['filter' => 'rejected'])
            ->assertSee($rejected->tracking_number)
            ->assertDontSee($submitted->tracking_number);
    }

    public function test_set_filter_updates_visible_applications(): void
    {
        $approved = $this->makeApplication(ApplicationStatus::Approved);
        $rejected = $this->makeApplication(ApplicationStatus::Rejected);

        Livewire::actingAs($this->user)
            ->test(ApplicationList::class)
            ->call('setFilter', 'approved')
            ->assertSee($approved->tracking_number)
            ->assertDontSee($rejected->tracking_number);
    }

    // -----------------------------------------------------------------------
    // ApplicationList — cross-user isolation
    // -----------------------------------------------------------------------

    public function test_applicant_cannot_see_other_users_applications(): void
    {
        $country = Country::factory()->create();
        $otherUser = User::factory()->create(['email_verified_at' => now()]);
        $otherUser->assignRole('applicant');
        $otherProfile = ApplicantProfile::factory()->create([
            'user_id' => $otherUser->id,
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
        ]);

        $myApp = $this->makeApplication(ApplicationStatus::Submitted);
        $otherApp = VisaApplication::factory()->submitted()->create([
            'applicant_profile_id' => $otherProfile->ulid,
        ]);

        Livewire::actingAs($this->user)
            ->test(ApplicationList::class)
            ->assertSee($myApp->tracking_number)
            ->assertDontSee($otherApp->tracking_number);
    }

    public function test_cross_user_isolation_holds_under_all_filters(): void
    {
        $country = Country::factory()->create();
        $otherUser = User::factory()->create(['email_verified_at' => now()]);
        $otherUser->assignRole('applicant');
        $otherProfile = ApplicantProfile::factory()->create([
            'user_id' => $otherUser->id,
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
        ]);

        $otherApp = VisaApplication::factory()->approved()->create([
            'applicant_profile_id' => $otherProfile->ulid,
        ]);

        foreach (['all', 'approved', 'in_review', 'submitted', 'rejected'] as $filter) {
            Livewire::actingAs($this->user)
                ->test(ApplicationList::class, ['filter' => $filter])
                ->assertDontSee($otherApp->tracking_number);
        }
    }

    // -----------------------------------------------------------------------
    // ApplicationDetail — open with own application
    // -----------------------------------------------------------------------

    public function test_application_detail_opens_own_application(): void
    {
        $app = $this->makeApplication(ApplicationStatus::Submitted);

        Livewire::actingAs($this->user)
            ->test(ApplicationDetail::class)
            ->dispatch('openDetail', applicationId: $app->ulid)
            ->assertSet('isOpen', true)
            ->assertSet('applicationId', $app->ulid);
    }

    public function test_application_detail_panel_shows_tracking_number(): void
    {
        $app = $this->makeApplication(ApplicationStatus::Submitted);

        Livewire::actingAs($this->user)
            ->test(ApplicationDetail::class)
            ->dispatch('openDetail', applicationId: $app->ulid)
            ->assertSee($app->tracking_number);
    }

    // -----------------------------------------------------------------------
    // ApplicationDetail — cross-user isolation
    // -----------------------------------------------------------------------

    public function test_application_detail_does_not_open_other_users_application(): void
    {
        $country = Country::factory()->create();
        $otherUser = User::factory()->create(['email_verified_at' => now()]);
        $otherUser->assignRole('applicant');
        $otherProfile = ApplicantProfile::factory()->create([
            'user_id' => $otherUser->id,
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
        ]);

        $otherApp = VisaApplication::factory()->submitted()->create([
            'applicant_profile_id' => $otherProfile->ulid,
        ]);

        Livewire::actingAs($this->user)
            ->test(ApplicationDetail::class)
            ->dispatch('openDetail', applicationId: $otherApp->ulid)
            ->assertSet('isOpen', false)
            ->assertSet('applicationId', null);
    }

    // -----------------------------------------------------------------------
    // ApplicationDetail — close
    // -----------------------------------------------------------------------

    public function test_application_detail_close_resets_state(): void
    {
        $app = $this->makeApplication(ApplicationStatus::Submitted);

        Livewire::actingAs($this->user)
            ->test(ApplicationDetail::class)
            ->dispatch('openDetail', applicationId: $app->ulid)
            ->assertSet('isOpen', true)
            ->call('close')
            ->assertSet('isOpen', false)
            ->assertSet('applicationId', null);
    }

    // -----------------------------------------------------------------------
    // ApplicationDetail — cancel action
    // -----------------------------------------------------------------------

    public function test_cancel_application_withdraws_draft_application(): void
    {
        $app = $this->makeApplication(ApplicationStatus::Draft);

        Livewire::actingAs($this->user)
            ->test(ApplicationDetail::class)
            ->dispatch('openDetail', applicationId: $app->ulid)
            ->call('cancelApplication')
            ->assertSet('isOpen', false);

        $this->assertDatabaseHas('visa_applications', [
            'ulid' => $app->ulid,
            'status' => ApplicationStatus::Withdrawn->value,
        ]);
    }

    public function test_cancel_application_withdraws_submitted_application(): void
    {
        $app = $this->makeApplication(ApplicationStatus::Submitted);

        Livewire::actingAs($this->user)
            ->test(ApplicationDetail::class)
            ->dispatch('openDetail', applicationId: $app->ulid)
            ->call('cancelApplication')
            ->assertSet('isOpen', false);

        $this->assertDatabaseHas('visa_applications', [
            'ulid' => $app->ulid,
            'status' => ApplicationStatus::Withdrawn->value,
        ]);
    }

    public function test_cancel_application_cannot_cancel_approved_application(): void
    {
        $app = $this->makeApplication(ApplicationStatus::Approved);

        Livewire::actingAs($this->user)
            ->test(ApplicationDetail::class)
            ->dispatch('openDetail', applicationId: $app->ulid)
            ->call('cancelApplication');

        $this->assertDatabaseHas('visa_applications', [
            'ulid' => $app->ulid,
            'status' => ApplicationStatus::Approved->value,
        ]);
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    /** @param array<string, mixed> $overrides */
    private function makeApplication(ApplicationStatus $status, array $overrides = []): VisaApplication
    {
        return VisaApplication::factory()->create(array_merge([
            'applicant_profile_id' => $this->profile->ulid,
            'status' => $status,
            'submitted_at' => $status === ApplicationStatus::Draft ? null : now(),
        ], $overrides));
    }
}
