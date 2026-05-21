<?php

namespace Tests\Feature;

use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Livewire\Documents\DocumentsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentsPageTest extends TestCase
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

    public function test_documents_page_requires_authentication(): void
    {
        $this->get(route('documents'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_applicant_can_access_documents_page(): void
    {
        $this->actingAs($this->user)
            ->get(route('documents'))
            ->assertOk();
    }

    public function test_empty_state_shown_when_no_documents(): void
    {
        Livewire::actingAs($this->user)
            ->test(DocumentsPage::class)
            ->assertSee('No documents yet');
    }

    public function test_applicant_sees_their_own_application_tracking_number(): void
    {
        $application = VisaApplication::factory()->submitted()->create([
            'applicant_profile_id' => $this->profile->ulid,
        ]);
        ApplicationDocument::factory()->create([
            'visa_application_id' => $application->ulid,
        ]);

        Livewire::actingAs($this->user)
            ->test(DocumentsPage::class)
            ->assertSee($application->tracking_number);
    }

    public function test_applicant_cannot_see_other_users_documents(): void
    {
        $country = Country::factory()->create();
        $otherUser = User::factory()->create();
        $otherUser->assignRole('applicant');
        $otherProfile = ApplicantProfile::factory()->create([
            'user_id' => $otherUser->id,
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
        ]);
        $otherApp = VisaApplication::factory()->submitted()->create([
            'applicant_profile_id' => $otherProfile->ulid,
        ]);
        ApplicationDocument::factory()->create([
            'visa_application_id' => $otherApp->ulid,
        ]);

        Livewire::actingAs($this->user)
            ->test(DocumentsPage::class)
            ->assertDontSee($otherApp->tracking_number);
    }

    public function test_documents_are_grouped_by_application(): void
    {
        $appA = VisaApplication::factory()->submitted()->create([
            'applicant_profile_id' => $this->profile->ulid,
        ]);
        $appB = VisaApplication::factory()->submitted()->create([
            'applicant_profile_id' => $this->profile->ulid,
        ]);
        ApplicationDocument::factory()->create(['visa_application_id' => $appA->ulid]);
        ApplicationDocument::factory()->create(['visa_application_id' => $appB->ulid]);

        Livewire::actingAs($this->user)
            ->test(DocumentsPage::class)
            ->assertSee($appA->tracking_number)
            ->assertSee($appB->tracking_number);
    }
}
