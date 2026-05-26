<?php

namespace App\Domain\Applications\Models;

use Database\Factories\ApplicationAnswerFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationAnswer extends Model
{
    /** @use HasFactory<ApplicationAnswerFactory> */
    use HasFactory, HasUlids;

    protected static function newFactory(): ApplicationAnswerFactory
    {
        return ApplicationAnswerFactory::new();
    }

    protected $primaryKey = 'ulid';

    protected $fillable = [
        'visa_application_id',
        'field_key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
        ];
    }

    public function visaApplication(): BelongsTo
    {
        return $this->belongsTo(VisaApplication::class, 'visa_application_id', 'ulid');
    }
}
