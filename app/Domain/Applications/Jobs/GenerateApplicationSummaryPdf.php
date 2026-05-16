<?php

namespace App\Domain\Applications\Jobs;

use App\Domain\Applications\Models\VisaApplication;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateApplicationSummaryPdf implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [10, 30, 60];

    public function __construct(public readonly string $visaApplicationUlid) {}

    public function handle(): void
    {
        $application = VisaApplication::with(['visaType', 'applicantProfile'])
            ->findOrFail($this->visaApplicationUlid);

        $applicantProfile = $application->applicantProfile;

        $pdf = Pdf::loadView('pdfs.application-summary', compact('application', 'applicantProfile'));

        $storagePath = 'summaries/'.Str::ulid().'.pdf';
        $written = Storage::disk('documents')->put($storagePath, $pdf->output());

        if (! $written) {
            $this->fail(new \RuntimeException("Failed to write application summary PDF to {$storagePath}"));

            return;
        }

        $application->update(['summary_pdf_path' => $storagePath]);
    }
}
