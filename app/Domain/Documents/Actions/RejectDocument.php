<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

class RejectDocument
{
    public function execute(ApplicationDocument $document, User $officer, string $reason): ApplicationDocument
    {
        return DB::transaction(function () use ($document, $officer, $reason) {
            $document->update([
                'status' => DocumentStatus::Rejected,
                'reviewed_by' => $officer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            AuditLogger::log('document.rejected', $document, ['reason' => $reason], $officer->id);

            return $document->fresh();
        });
    }
}
