<?php

namespace App\Support;

use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public static function log(
        string $action,
        object $subject,
        array $metadata = [],
        ?int $userId = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $userId,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'metadata' => $metadata,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }
}
