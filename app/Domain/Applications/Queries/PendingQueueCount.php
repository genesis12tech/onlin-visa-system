<?php

namespace App\Domain\Applications\Queries;

use App\Support\MockOfficerData;

class PendingQueueCount
{
    public function count(): int
    {
        return MockOfficerData::get('stats')['pending_queue']['value'] ?? 0;
    }
}
