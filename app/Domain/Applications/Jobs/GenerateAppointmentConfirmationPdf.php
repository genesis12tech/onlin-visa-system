<?php

namespace App\Domain\Applications\Jobs;

use App\Domain\Applications\Models\ApplicationAppointment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateAppointmentConfirmationPdf implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [10, 30, 60];

    public function __construct(public readonly string $appointmentUlid) {}

    public function handle(): void
    {
        $appointment = ApplicationAppointment::with([
            'visaApplication.visaType',
            'visaApplication.applicantProfile',
        ])->findOrFail($this->appointmentUlid);

        $application = $appointment->visaApplication;
        $applicantProfile = $application->applicantProfile;

        $pdf = Pdf::loadView('pdfs.appointment-confirmation', compact('appointment', 'application', 'applicantProfile'));

        $storagePath = 'appointments/'.Str::ulid().'.pdf';
        $written = Storage::disk('documents')->put($storagePath, $pdf->output());

        if (! $written) {
            $this->fail(new \RuntimeException("Failed to write appointment confirmation PDF to {$storagePath}"));

            return;
        }

        $appointment->update(['confirmation_pdf_path' => $storagePath]);
    }
}
