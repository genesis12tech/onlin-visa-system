<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\OfficerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_officer_profile_can_be_created_for_a_user(): void
    {
        $user = User::factory()->create();

        $profile = OfficerProfile::create([
            'user_id' => $user->id,
            'display_initials' => 'PM',
            'capacity' => 12,
            'specialisations' => ['Tourist', 'Business'],
            'avatar_color' => 'indigo',
            'is_accepting_assignments' => true,
        ]);

        $this->assertDatabaseHas('officer_profiles', [
            'user_id' => $user->id,
            'display_initials' => 'PM',
            'capacity' => 12,
        ]);
        $this->assertInstanceOf(OfficerProfile::class, $profile);
    }

    public function test_user_has_officer_profile_relationship(): void
    {
        $user = User::factory()->create();
        $profile = OfficerProfile::factory()->for($user)->create();

        $this->assertTrue($user->officerProfile->is($profile));
    }

    public function test_officer_profile_is_soft_deleted(): void
    {
        $user = User::factory()->create();
        $profile = OfficerProfile::factory()->for($user)->create();

        $profile->delete();

        $this->assertSoftDeleted('officer_profiles', ['user_id' => $user->id]);
    }

    public function test_officer_profile_factory_state_inactive(): void
    {
        $user = User::factory()->create();
        $profile = OfficerProfile::factory()->inactive()->for($user)->create();

        $this->assertFalse($profile->is_accepting_assignments);
    }
}
