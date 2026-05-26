<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\OfficerProfile;
use App\Models\User;
use Database\Seeders\OfficerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficerSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_officer_seeder_creates_four_officers(): void
    {
        $this->seed(OfficerSeeder::class);

        $this->assertEquals(4, OfficerProfile::count());
    }

    public function test_officer_seeder_creates_priya_mehta_with_correct_initials(): void
    {
        $this->seed(OfficerSeeder::class);

        $user = User::where('email', 'priya.mehta@example.com')->firstOrFail();
        $profile = $user->officerProfile;

        $this->assertNotNull($profile);
        $this->assertEquals('PM', $profile->display_initials);
    }

    public function test_officer_seeder_creates_rahul_sharma(): void
    {
        $this->seed(OfficerSeeder::class);

        $user = User::where('email', 'rahul.sharma@example.com')->firstOrFail();
        $this->assertEquals('RS', $user->officerProfile->display_initials);
    }

    public function test_officer_seeder_creates_anita_desai(): void
    {
        $this->seed(OfficerSeeder::class);

        $user = User::where('email', 'anita.desai@example.com')->firstOrFail();
        $this->assertEquals('AD', $user->officerProfile->display_initials);
    }

    public function test_officer_seeder_creates_mohammed_khan(): void
    {
        $this->seed(OfficerSeeder::class);

        $user = User::where('email', 'mohammed.khan@example.com')->firstOrFail();
        $this->assertEquals('MK', $user->officerProfile->display_initials);
    }

    public function test_officer_seeder_is_idempotent(): void
    {
        $this->seed(OfficerSeeder::class);
        $this->seed(OfficerSeeder::class);

        $this->assertEquals(4, OfficerProfile::count());
    }

    public function test_officers_are_assigned_case_officer_role(): void
    {
        $this->seed(OfficerSeeder::class);

        $user = User::where('email', 'priya.mehta@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('case_officer'));
    }
}
