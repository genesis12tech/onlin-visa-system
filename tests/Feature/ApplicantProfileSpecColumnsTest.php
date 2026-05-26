<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\ApplicantProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ApplicantProfileSpecColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_applicant_profile_has_profile_completed_at_column(): void
    {
        $profile = ApplicantProfile::factory()->create(['profile_completed_at' => now()]);

        $this->assertNotNull($profile->fresh()->profile_completed_at);
        $this->assertInstanceOf(Carbon::class, $profile->fresh()->profile_completed_at);
    }

    public function test_applicant_profile_supports_soft_delete(): void
    {
        $profile = ApplicantProfile::factory()->create();
        $ulid = $profile->ulid;

        $profile->delete();

        $this->assertSoftDeleted('applicant_profiles', ['ulid' => $ulid]);
        $this->assertNull(ApplicantProfile::find($ulid));
        $this->assertNotNull(ApplicantProfile::withTrashed()->find($ulid));
    }

    public function test_profile_completed_at_is_null_by_default(): void
    {
        $profile = ApplicantProfile::factory()->create();

        $this->assertNull($profile->fresh()->profile_completed_at);
    }
}
