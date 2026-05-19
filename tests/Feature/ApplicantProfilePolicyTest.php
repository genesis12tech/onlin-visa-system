<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\ApplicantProfile;
use App\Models\User;
use App\Policies\ApplicantProfilePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApplicantProfilePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['admin', 'super_admin', 'case_officer', 'senior_officer', 'applicant'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_applicant_can_view_own_profile(): void
    {
        $applicant = User::factory()->create();
        $applicant->assignRole('applicant');
        $profile = ApplicantProfile::factory()->create(['user_id' => $applicant->id]);

        $this->assertTrue((new ApplicantProfilePolicy)->view($applicant, $profile));
    }

    public function test_applicant_cannot_view_another_applicants_profile(): void
    {
        $applicant = User::factory()->create();
        $applicant->assignRole('applicant');
        $other = User::factory()->create();
        $profile = ApplicantProfile::factory()->create(['user_id' => $other->id]);

        $this->assertFalse((new ApplicantProfilePolicy)->view($applicant, $profile));
    }

    public function test_case_officer_can_view_any_profile(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');
        $profile = ApplicantProfile::factory()->create();

        $this->assertTrue((new ApplicantProfilePolicy)->view($officer, $profile));
    }

    public function test_senior_officer_can_view_any_profile(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('senior_officer');
        $profile = ApplicantProfile::factory()->create();

        $this->assertTrue((new ApplicantProfilePolicy)->view($officer, $profile));
    }

    public function test_super_admin_can_view_any_profile(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $profile = ApplicantProfile::factory()->create();

        $this->assertTrue((new ApplicantProfilePolicy)->view($admin, $profile));
    }

    public function test_officer_can_view_any_profiles(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');

        $this->assertTrue((new ApplicantProfilePolicy)->viewAny($officer));
    }

    public function test_applicant_cannot_view_any_profiles(): void
    {
        $applicant = User::factory()->create();
        $applicant->assignRole('applicant');

        $this->assertFalse((new ApplicantProfilePolicy)->viewAny($applicant));
    }

    public function test_anyone_can_create_profile(): void
    {
        $applicant = User::factory()->create();
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');

        $this->assertTrue((new ApplicantProfilePolicy)->create($applicant));
        $this->assertTrue((new ApplicantProfilePolicy)->create($officer));
    }

    public function test_applicant_can_update_own_profile(): void
    {
        $applicant = User::factory()->create();
        $applicant->assignRole('applicant');
        $profile = ApplicantProfile::factory()->create(['user_id' => $applicant->id]);

        $this->assertTrue((new ApplicantProfilePolicy)->update($applicant, $profile));
    }

    public function test_applicant_cannot_update_another_applicants_profile(): void
    {
        $applicant = User::factory()->create();
        $applicant->assignRole('applicant');
        $other = User::factory()->create();
        $profile = ApplicantProfile::factory()->create(['user_id' => $other->id]);

        $this->assertFalse((new ApplicantProfilePolicy)->update($applicant, $profile));
    }

    public function test_case_officer_cannot_update_applicant_profile(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');
        $profile = ApplicantProfile::factory()->create();

        $this->assertFalse((new ApplicantProfilePolicy)->update($officer, $profile));
    }

    public function test_super_admin_can_update_any_profile(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $profile = ApplicantProfile::factory()->create();

        $this->assertTrue((new ApplicantProfilePolicy)->update($admin, $profile));
    }

    public function test_admin_can_update_any_profile(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $profile = ApplicantProfile::factory()->create();

        $this->assertTrue((new ApplicantProfilePolicy)->update($admin, $profile));
    }

    public function test_only_super_admin_can_delete_profile(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $applicant = User::factory()->create();
        $applicant->assignRole('applicant');
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');
        $profile = ApplicantProfile::factory()->create(['user_id' => $applicant->id]);

        $this->assertTrue((new ApplicantProfilePolicy)->delete($admin, $profile));
        $this->assertFalse((new ApplicantProfilePolicy)->delete($applicant, $profile));
        $this->assertFalse((new ApplicantProfilePolicy)->delete($officer, $profile));
    }

    public function test_cross_user_isolation_profile_access(): void
    {
        $user1 = User::factory()->create();
        $user1->assignRole('applicant');
        $user2 = User::factory()->create();
        $user2->assignRole('applicant');

        $profile1 = ApplicantProfile::factory()->create(['user_id' => $user1->id]);
        $profile2 = ApplicantProfile::factory()->create(['user_id' => $user2->id]);

        $policy = new ApplicantProfilePolicy;

        // Each user can only view their own profile
        $this->assertTrue($policy->view($user1, $profile1));
        $this->assertFalse($policy->view($user1, $profile2));

        $this->assertTrue($policy->view($user2, $profile2));
        $this->assertFalse($policy->view($user2, $profile1));

        // Each user can only update their own profile
        $this->assertTrue($policy->update($user1, $profile1));
        $this->assertFalse($policy->update($user1, $profile2));

        $this->assertTrue($policy->update($user2, $profile2));
        $this->assertFalse($policy->update($user2, $profile1));
    }
}
