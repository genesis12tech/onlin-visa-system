<?php

namespace App\Jobs;

use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Reporting\Enums\ExportStatus;
use App\Domain\Reporting\Models\ApplicationExport;
use App\Support\AuditLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ExportApplicationsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public int $backoff = 60;

    public function __construct(
        public readonly array $filters = [],
        public readonly int $requestedBy = 0,
    ) {
        $this->onQueue('reports');
    }

    public function handle(): void
    {
        $export = ApplicationExport::create([
            'requested_by' => $this->requestedBy,
            'filters' => $this->filters,
            'status' => ExportStatus::Processing,
        ]);

        try {
            $query = VisaApplication::with(['visaType', 'applicantProfile.nationality'])
                ->whereNotNull('submitted_at')
                ->latest('submitted_at');

            if (! empty($this->filters['status'])) {
                $query->where('status', $this->filters['status']);
            }

            if (! empty($this->filters['visa_type_id'])) {
                $query->where('visa_type_id', $this->filters['visa_type_id']);
            }

            $filePath = 'exports/'.Str::ulid().'.csv';
            $handle = tmpfile();
            $rowCount = 0;

            fputcsv($handle, [
                'tracking_number',
                'visa_type',
                'status',
                'nationality',
                'submitted_at',
                'decision_at',
                'travel_date',
            ]);

            $query->chunk(500, function ($applications) use ($handle, &$rowCount): void {
                foreach ($applications as $app) {
                    fputcsv($handle, [
                        $app->tracking_number,
                        $app->visaType?->name ?? '',
                        $app->status->value,
                        $app->applicantProfile?->nationality?->name ?? '',
                        $app->submitted_at?->toDateTimeString() ?? '',
                        $app->decision_at?->toDateTimeString() ?? '',
                        $app->travel_date?->toDateString() ?? '',
                    ]);
                    $rowCount++;
                }
            });

            rewind($handle);
            Storage::disk('local')->put($filePath, stream_get_contents($handle));
            fclose($handle);

            $export->update([
                'status' => ExportStatus::Ready,
                'file_path' => $filePath,
                'row_count' => $rowCount,
                'generated_at' => now(),
            ]);

            if ($this->requestedBy) {
                AuditLogger::log('export.generated', $export, [
                    'row_count' => $rowCount,
                    'file_path' => $filePath,
                ], $this->requestedBy);
            }
        } catch (Throwable $e) {
            $export->update(['status' => ExportStatus::Failed]);
            throw $e;
        }
    }
}
