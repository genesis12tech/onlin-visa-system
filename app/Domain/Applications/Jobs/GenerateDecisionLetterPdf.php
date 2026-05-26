<?php

namespace App\Domain\Applications\Jobs;

use App\Domain\Applications\Models\ApplicationSnapshot;
use App\Domain\Applications\Models\VisaApplication;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateDecisionLetterPdf implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [10, 30, 60];

    public function __construct(public readonly string $visaApplicationUlid) {}

    public function handle(): void
    {
        // Load only the decision-specific fields from the live record.
        $application = VisaApplication::findOrFail($this->visaApplicationUlid);

        // Pull immutable identity data from the snapshot taken at submission time
        // so that post-decision edits to the applicant profile or visa type cannot
        // alter the contents of an already-issued decision letter.
        $snapshot = ApplicationSnapshot::where('visa_application_id', $this->visaApplicationUlid)->first();

        $applicantName = $snapshot?->snapshot_data['applicant_name']
            ?? $application->applicantProfile?->full_name
            ?? '—';

        $visaTypeName = $snapshot?->snapshot_data['visa_type']['name']
            ?? $application->visaType?->name
            ?? '—';

        $pdf = Pdf::loadView('pdfs.decision-letter', [
            'application' => $application,
            'applicantName' => $applicantName,
            'visaTypeName' => $visaTypeName,
        ]);

        $storagePath = 'decisions/'.Str::ulid().'.pdf';
        $written = Storage::disk('documents')->put($storagePath, $pdf->output());

        if (! $written) {
            $this->fail(new \RuntimeException("Failed to write decision letter PDF to {$storagePath}"));

            return;
        }

        $application->update(['decision_letter_pdf_path' => $storagePath]);
    }
}
