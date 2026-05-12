<?php

namespace App\Domain\Payments\Models;

use App\Domain\Applications\Models\VisaType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisaFee extends Model
{
    use HasUlids;

    protected $primaryKey = 'ulid';

    protected $fillable = [
        'visa_type_id',
        'name',
        'amount',
        'currency',
        'applicant_type',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function visaType(): BelongsTo
    {
        return $this->belongsTo(VisaType::class, 'visa_type_id', 'ulid');
    }
}
