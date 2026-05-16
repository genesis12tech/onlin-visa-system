<?php

namespace Tests\Feature;

use App\Domain\Reporting\Enums\ExportStatus;
use App\Domain\Reporting\Models\ApplicationExport;
use App\Filament\Resources\VisaApplications\Pages\ListVisaApplications;
use App\Jobs\ExportApplicationsJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApplicationExportResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['super_admin', 'admin', 'case_officer', 'senior_officer', 'finance_officer', 'applicant'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_export_action_dispatches_job_to_reports_queue(): void
    {
        Queue::fake();
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)
            ->test(ListVisaApplications::class)
            ->callAction('exportCsv')
            ->assertNotified();

        Queue::assertPushedOn('reports', ExportApplicationsJob::class);
    }

    public function test_export_download_requires_authentication(): void
    {
        $user = User::factory()->create();

        $export = ApplicationExport::create([
            'requested_by' => $user->id,
            'filters' => [],
            'status' => ExportStatus::Ready,
            'file_path' => 'exports/test.csv',
            'row_count' => 0,
            'generated_at' => now(),
        ]);

        $this->get(route('exports.download', $export->ulid))
            ->assertRedirect();
    }

    public function test_user_cannot_download_another_users_export(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $export = ApplicationExport::create([
            'requested_by' => $owner->id,
            'filters' => [],
            'status' => ExportStatus::Ready,
            'file_path' => 'exports/test.csv',
            'row_count' => 0,
            'generated_at' => now(),
        ]);

        $this->actingAs($other)
            ->get(route('exports.download', $export->ulid))
            ->assertForbidden();
    }
}
