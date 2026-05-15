<?php

namespace App\Domain\Documents\Jobs;

use App\Domain\Documents\Enums\ScanStatus;
use App\Domain\Documents\Models\DocumentVersion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScanDocumentVersionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public array $backoff = [5, 15, 30];

    public function __construct(public readonly string $documentVersionUlid) {}

    public function handle(): void
    {
        $version = DocumentVersion::findOrFail($this->documentVersionUlid);

        if ($version->scan_status !== ScanStatus::Pending) {
            return;
        }

        // MVP: simulate a clean scan. Replace with a real AV API call in production.
        $version->update([
            'scan_status' => ScanStatus::Clean,
            'scan_completed_at' => now(),
        ]);
    }
}
