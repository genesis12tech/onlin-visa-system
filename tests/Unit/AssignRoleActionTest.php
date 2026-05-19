<?php

namespace Tests\Unit;

use App\Domain\Identity\Actions\AssignRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AssignRoleActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'case_officer', 'guard_name' => 'web']);
    }

    public function test_syncs_role_to_target_user(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('super_admin');
        $target = User::factory()->create();
        $target->assignRole('admin');

        $updated = AssignRole::run($target, 'case_officer', $actor);

        $this->assertTrue($updated->hasRole('case_officer'));
        $this->assertFalse($updated->hasRole('admin'));
    }

    public function test_writes_activity_log_entry(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('super_admin');
        $target = User::factory()->create();
        $target->assignRole('admin');

        AssignRole::run($target, 'case_officer', $actor);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'admin.role_assigned',
            'causer_id' => $actor->id,
            'subject_id' => $target->id,
        ]);
    }
}
