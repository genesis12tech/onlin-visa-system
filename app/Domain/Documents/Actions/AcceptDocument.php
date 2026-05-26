<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\ScanStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

class AcceptDocument
{
    public function execute(ApplicationDocument $document, User $officer): ApplicationDocument
    {
        if ($document->currentVersion?->scan_status !== ScanStatus::Clean) {
            throw new \RuntimeException('Document cannot be accepted until the virus scan is complete and clean.');
        }

        return DB::transaction(function () use ($document, $officer) {
            $document->update([
                'status' => DocumentStatus::Accepted,
                'reviewed_by' => $officer->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            AuditLogger::log('document.accepted', $document, [], $officer->id);

            return $document->fresh();
        });
    }
}
