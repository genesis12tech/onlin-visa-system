<?php

namespace App\Domain\Reporting\Models;

use App\Domain\Applications\Models\VisaType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyApplicationMetrics extends Model
{
    protected $fillable = [
        'date',
        'officer_id',
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
            'date' => 'date:Y-m-d',
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

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id');
    }

    public function scopePerVisaType(Builder $query): Builder
    {
        return $query->whereNull('officer_id');
    }

    public function scopePerOfficer(Builder $query): Builder
    {
        return $query->whereNotNull('officer_id');
    }
}
