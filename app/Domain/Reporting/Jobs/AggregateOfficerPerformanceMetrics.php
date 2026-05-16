<?php

namespace App\Domain\Reporting\Jobs;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Reporting\Models\OfficerPerformanceMetrics;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class AggregateOfficerPerformanceMetrics implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly string $date, // Y-m-d
    ) {
        $this->onQueue('reports');
    }

    public function handle(): void
    {
        $date = Carbon::parse($this->date);
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $decisionStatuses = [
            ApplicationStatus::Approved->value,
            ApplicationStatus::Rejected->value,
            ApplicationStatus::AdditionalInfoRequested->value,
            ApplicationStatus::DocsRequired->value,
        ];

        $actorIds = ApplicationStatusHistory::whereIn('to_status', $decisionStatuses)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('actor_id')
            ->distinct()
            ->pluck('actor_id');

        foreach ($actorIds as $officerId) {
            $histories = ApplicationStatusHistory::where('actor_id', $officerId)
                ->whereIn('to_status', $decisionStatuses)
                ->whereBetween('created_at', [$start, $end])
                ->get('to_status');

            OfficerPerformanceMetrics::updateOrCreate(
                ['date' => $date->startOfDay(), 'officer_id' => $officerId],
                [
                    'reviewed_count' => $histories->count(),
                    'approved_count' => $histories->where('to_status', ApplicationStatus::Approved->value)->count(),
                    'rejected_count' => $histories->where('to_status', ApplicationStatus::Rejected->value)->count(),
                    'info_requested_count' => $histories->whereIn('to_status', [
                        ApplicationStatus::AdditionalInfoRequested->value,
                        ApplicationStatus::DocsRequired->value,
                    ])->count(),
                ]
            );
        }
    }
}
