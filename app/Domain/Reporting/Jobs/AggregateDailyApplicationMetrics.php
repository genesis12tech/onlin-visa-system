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
                ['date' => $date->toDateString(), 'officer_id' => null, 'visa_type_id' => $visaType->ulid],
                [
                    'submitted_count' => $submitted,
                    'approved_count' => $approved,
                    'rejected_count' => $rejected,
                    'pending_count' => $pending,
                    'avg_processing_days' => $avgDays,
                ]
            );
        });

        // Per-officer aggregation: aggregate for any user who made decisions on this date
        $officerIds = VisaApplication::whereNotNull('decision_by')
            ->whereBetween('decision_at', [$start, $end])
            ->distinct()
            ->pluck('decision_by');

        $officerIds->each(function (int|string $officerId) use ($start, $end, $date): void {
            $approved = VisaApplication::where('decision_by', $officerId)
                ->where('status', ApplicationStatus::Approved->value)
                ->whereBetween('decision_at', [$start, $end])
                ->count();

            $rejected = VisaApplication::where('decision_by', $officerId)
                ->where('status', ApplicationStatus::Rejected->value)
                ->whereBetween('decision_at', [$start, $end])
                ->count();

            $assigned = VisaApplication::where('assigned_officer_id', $officerId)
                ->whereIn('status', [
                    ApplicationStatus::UnderReview->value,
                    ApplicationStatus::AdditionalInfoRequested->value,
                ])
                ->count();

            $avgDays = VisaApplication::where('decision_by', $officerId)
                ->whereNotNull('decision_at')
                ->whereNotNull('submitted_at')
                ->whereBetween('decision_at', [$start, $end])
                ->get(['submitted_at', 'decision_at'])
                ->avg(fn ($app) => $app->submitted_at->diffInDays($app->decision_at));

            DailyApplicationMetrics::updateOrCreate(
                ['date' => $date->toDateString(), 'officer_id' => $officerId, 'visa_type_id' => null],
                [
                    'submitted_count' => 0,
                    'approved_count' => $approved,
                    'rejected_count' => $rejected,
                    'pending_count' => $assigned,
                    'avg_processing_days' => $avgDays,
                ]
            );
        });
    }
}
