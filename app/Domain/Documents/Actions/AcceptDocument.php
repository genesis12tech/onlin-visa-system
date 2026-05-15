<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

class AcceptDocument
{
    public function execute(ApplicationDocument $document, User $officer): ApplicationDocument
    {
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
