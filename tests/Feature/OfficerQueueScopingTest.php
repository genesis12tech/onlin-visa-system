<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use App\Filament\Officer\Resources\VisaApplications\OfficerVisaApplicationResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OfficerQueueScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['case_officer', 'senior_officer', 'admin', 'super_admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_case_officer_query_returns_only_assigned_applications(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        $other = User::factory()->create(['email_verified_at' => now()]);
        $other->assignRole('case_officer');

        $mine = VisaApplication::factory()->create([
            'assigned_officer_id' => $officer->id,
            'status' => ApplicationStatus::UnderReview,
        ]);

        $notMine = VisaApplication::factory()->create([
            'assigned_officer_id' => $other->id,
            'status' => ApplicationStatus::UnderReview,
        ]);

        $unassigned = VisaApplication::factory()->create([
            'assigned_officer_id' => null,
            'status' => ApplicationStatus::UnderReview,
        ]);

        $this->actingAs($officer);

        $ids = OfficerVisaApplicationResource::getEloquentQuery()->pluck('visa_applications.ulid');

        $this->assertContains($mine->ulid, $ids);
        $this->assertNotContains($notMine->ulid, $ids);
        $this->assertNotContains($unassigned->ulid, $ids);
    }

    public function test_senior_officer_query_returns_all_applications(): void
    {
        $senior = User::factory()->create(['email_verified_at' => now()]);
        $senior->assignRole('senior_officer');

        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        $assigned = VisaApplication::factory()->create([
            'assigned_officer_id' => $officer->id,
            'status' => ApplicationStatus::UnderReview,
        ]);

        $unassigned = VisaApplication::factory()->create([
            'assigned_officer_id' => null,
            'status' => ApplicationStatus::UnderReview,
        ]);

        $this->actingAs($senior);

        $ids = OfficerVisaApplicationResource::getEloquentQuery()->pluck('visa_applications.ulid');

        $this->assertContains($assigned->ulid, $ids);
        $this->assertContains($unassigned->ulid, $ids);
    }

    public function test_admin_query_returns_all_applications(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        $app1 = VisaApplication::factory()->create(['status' => ApplicationStatus::UnderReview]);
        $app2 = VisaApplication::factory()->create(['status' => ApplicationStatus::UnderReview]);

        $this->actingAs($admin);

        $ids = OfficerVisaApplicationResource::getEloquentQuery()->pluck('visa_applications.ulid');

        $this->assertContains($app1->ulid, $ids);
        $this->assertContains($app2->ulid, $ids);
    }

    public function test_case_officer_with_no_assignments_sees_empty_queue(): void
    {
        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('case_officer');

        VisaApplication::factory()->create([
            'assigned_officer_id' => null,
            'status' => ApplicationStatus::UnderReview,
        ]);

        $this->actingAs($officer);

        $count = OfficerVisaApplicationResource::getEloquentQuery()->count();

        $this->assertEquals(0, $count);
    }
}
