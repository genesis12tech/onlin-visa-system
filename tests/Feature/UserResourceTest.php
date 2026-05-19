<?php

namespace Tests\Feature;

use App\Domain\Identity\Enums\UserStatus;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['super_admin', 'admin', 'case_officer', 'applicant'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super_admin');
    }

    public function test_user_resource_cannot_delete_any_record(): void
    {
        $target = User::factory()->create();

        $this->assertFalse(UserResource::canDelete($target));
        $this->assertFalse(UserResource::canDeleteAny());
    }

    public function test_assign_role_action_changes_user_role(): void
    {
        $target = User::factory()->create();
        $target->assignRole('admin');

        Livewire::actingAs($this->superAdmin)
            ->test(ListUsers::class)
            ->callAction(
                TestAction::make('assign_role')->table($target),
                ['role' => 'case_officer'],
            )
            ->assertHasNoActionErrors();

        $this->assertTrue($target->fresh()->hasRole('case_officer'));
    }

    public function test_suspend_action_sets_status_to_suspended(): void
    {
        $target = User::factory()->create();
        $target->assignRole('admin');

        Livewire::actingAs($this->superAdmin)
            ->test(ListUsers::class)
            ->callAction(TestAction::make('suspend')->table($target))
            ->assertHasNoActionErrors();

        $this->assertSame(UserStatus::Suspended->value, $target->fresh()->status->value);
    }
}
