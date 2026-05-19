<?php

namespace Tests\Unit;

use App\Domain\Identity\Actions\SuspendUser;
use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SuspendUserActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_sets_user_status_to_suspended(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('super_admin');
        $target = User::factory()->create();
        $target->assignRole('admin');

        SuspendUser::run($target, $actor);

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'status' => UserStatus::Suspended->value,
        ]);
    }

    public function test_cannot_suspend_a_super_admin(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('super_admin');
        $target = User::factory()->create();
        $target->assignRole('super_admin');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Super admins cannot be suspended.');

        SuspendUser::run($target, $actor);
    }

    public function test_writes_activity_log_entry(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('super_admin');
        $target = User::factory()->create();
        $target->assignRole('admin');

        SuspendUser::run($target, $actor);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'user.suspended',
            'causer_id' => $actor->id,
            'subject_id' => $target->id,
        ]);
    }
}
