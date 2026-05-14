<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExportApplicationsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly array $filters = [],
        public readonly int $requestedBy = 0,
    ) {
        $this->onQueue('reports');
    }

    public function handle(): void
    {
        // CSV/XLSX generation dispatched to the reports queue.
        // Implementation in Milestone 7 — Reporting phase.
    }
}
