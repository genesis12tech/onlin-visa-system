<?php

namespace App\Domain\Reporting\Jobs;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Reporting\Models\DocumentRejectionMetrics;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class AggregateDocumentRejectionMetrics implements ShouldQueue
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

        $rejections = ApplicationDocument::where('status', DocumentStatus::Rejected)
            ->whereBetween('reviewed_at', [$start, $end])
            ->whereNotNull('document_type_id')
            ->whereNotNull('rejection_reason')
            ->get(['document_type_id', 'rejection_reason'])
            ->groupBy('document_type_id');

        foreach ($rejections as $documentTypeId => $docs) {
            $topReasons = $docs
                ->groupBy('rejection_reason')
                ->map(fn ($group, $reason) => ['reason' => (string) $reason, 'count' => $group->count()])
                ->sortByDesc('count')
                ->values()
                ->take(5)
                ->toArray();

            DocumentRejectionMetrics::updateOrCreate(
                ['date' => $date->startOfDay(), 'document_type_id' => $documentTypeId],
                [
                    'rejection_count' => $docs->count(),
                    'top_reasons' => $topReasons,
                ]
            );
        }
    }
}
