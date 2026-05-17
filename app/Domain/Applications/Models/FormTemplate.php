<?php

namespace App\Domain\Applications\Models;

use Database\Factories\FormTemplateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormTemplate extends Model
{
    /** @use HasFactory<FormTemplateFactory> */
    use HasFactory, HasUlids;

    protected static function newFactory(): FormTemplateFactory
    {
        return FormTemplateFactory::new();
    }

    protected $primaryKey = 'ulid';

    protected $fillable = [
        'visa_type_id',
        'name',
        'version',
        'schema',
        'is_active',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'version' => 'integer',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function visaType(): BelongsTo
    {
        return $this->belongsTo(VisaType::class, 'visa_type_id', 'ulid');
    }
}
