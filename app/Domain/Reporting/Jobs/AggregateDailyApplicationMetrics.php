<?php

namespace App\Domain\Reporting\Jobs;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Reporting\Models\DailyApplicationMetrics;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class AggregateDailyApplicationMetrics implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $backoff = 30;

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

        VisaType::all()->each(function (VisaType $visaType) use ($start, $end, $date): void {
            $submitted = VisaApplication::where('visa_type_id', $visaType->ulid)
                ->whereBetween('submitted_at', [$start, $end])
                ->count();

            $approved = VisaApplication::where('visa_type_id', $visaType->ulid)
                ->where('status', ApplicationStatus::Approved->value)
                ->whereBetween('decision_at', [$start, $end])
                ->count();

            $rejected = VisaApplication::where('visa_type_id', $visaType->ulid)
                ->where('status', ApplicationStatus::Rejected->value)
                ->whereBetween('decision_at', [$start, $end])
                ->count();

            $pending = VisaApplication::where('visa_type_id', $visaType->ulid)
                ->whereIn('status', [
                    ApplicationStatus::Submitted->value,
                    ApplicationStatus::UnderReview->value,
                    ApplicationStatus::DocsRequired->value,
                    ApplicationStatus::AdditionalInfoRequested->value,
                ])
                ->count();

            $avgDays = VisaApplication::where('visa_type_id', $visaType->ulid)
                ->whereNotNull('decision_at')
                ->whereNotNull('submitted_at')
                ->whereBetween('decision_at', [$start, $end])
                ->get(['submitted_at', 'decision_at'])
                ->avg(fn ($app) => $app->submitted_at->diffInDays($app->decision_at));

            DailyApplicationMetrics::updateOrCreate(
                ['date' => $date->startOfDay(), 'visa_type_id' => $visaType->ulid],
                [
                    'submitted_count' => $submitted,
                    'approved_count' => $approved,
                    'rejected_count' => $rejected,
                    'pending_count' => $pending,
                    'avg_processing_days' => $avgDays,
                ]
            );
        });
    }
}
