<?php

namespace Tests\Feature;

use App\Jobs\ExportApplicationsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExportIsQueuedTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_job_is_dispatched_to_queue(): void
    {
        Queue::fake();

        ExportApplicationsJob::dispatch(['ulid-1', 'ulid-2'], 1);

        Queue::assertPushed(ExportApplicationsJob::class, function ($job) {
            return $job->filters === ['ulid-1', 'ulid-2']
                && $job->requestedBy === 1;
        });
    }

    public function test_export_job_targets_reports_queue(): void
    {
        Queue::fake();

        ExportApplicationsJob::dispatch(['ulid-1'], 1);

        Queue::assertPushedOn('reports', ExportApplicationsJob::class);
    }

    public function test_export_does_not_generate_file_inline(): void
    {
        Queue::fake();

        ExportApplicationsJob::dispatch([], 1);

        // No synchronous file creation should occur — the job handle() is a no-op until M7
        Queue::assertPushed(ExportApplicationsJob::class);
    }
}
