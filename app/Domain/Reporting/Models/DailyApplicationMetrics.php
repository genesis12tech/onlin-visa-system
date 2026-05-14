<?php

namespace App\Domain\Reporting\Models;

use App\Domain\Applications\Models\VisaType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyApplicationMetrics extends Model
{
    protected $fillable = [
        'date',
        'visa_type_id',
        'submitted_count',
        'approved_count',
        'rejected_count',
        'pending_count',
        'avg_processing_days',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'submitted_count' => 'integer',
            'approved_count' => 'integer',
            'rejected_count' => 'integer',
            'pending_count' => 'integer',
            'avg_processing_days' => 'float',
        ];
    }

    public function visaType(): BelongsTo
    {
        return $this->belongsTo(VisaType::class, 'visa_type_id', 'ulid');
    }
}
