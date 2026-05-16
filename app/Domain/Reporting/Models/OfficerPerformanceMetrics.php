<?php

namespace App\Domain\Reporting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficerPerformanceMetrics extends Model
{
    protected $fillable = [
        'date',
        'officer_id',
        'reviewed_count',
        'approved_count',
        'rejected_count',
        'info_requested_count',
        'avg_review_hours',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'reviewed_count' => 'integer',
            'approved_count' => 'integer',
            'rejected_count' => 'integer',
            'info_requested_count' => 'integer',
            'avg_review_hours' => 'float',
        ];
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id');
    }
}
