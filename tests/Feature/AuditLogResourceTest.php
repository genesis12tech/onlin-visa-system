<?php

namespace Tests\Feature;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\User;
use App\Support\AuditLog;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuditLogResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_admin_can_access_audit_log_index(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->get(AuditLogResource::getUrl('index'))
            ->assertSuccessful();
    }

    public function test_audit_log_resource_cannot_create(): void
    {
        $this->assertFalse(AuditLogResource::canCreate());
    }

    public function test_audit_log_entries_appear_in_table(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        AuditLogger::log('test.action', $admin, ['key' => 'value'], $admin->id);

        Livewire::actingAs($admin)
            ->test(ListAuditLogs::class)
            ->assertCanSeeTableRecords(AuditLog::all());
    }
}
