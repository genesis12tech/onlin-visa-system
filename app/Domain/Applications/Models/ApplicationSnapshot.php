<?php

namespace App\Domain\Applications\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationSnapshot extends Model
{
    use HasUlids;

    protected $primaryKey = 'ulid';

    public $timestamps = false;

    protected $fillable = [
        'visa_application_id',
        'snapshot_data',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_data' => 'encrypted:array',
            'created_at' => 'datetime',
        ];
    }

    public function visaApplication(): BelongsTo
    {
        return $this->belongsTo(VisaApplication::class, 'visa_application_id', 'ulid');
    }
}
