<?php

namespace App\Domain\Reporting\Models;

use App\Domain\Documents\Models\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRejectionMetrics extends Model
{
    protected $fillable = [
        'date',
        'document_type_id',
        'rejection_count',
        'top_reasons',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'rejection_count' => 'integer',
            'top_reasons' => 'array',
        ];
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id', 'ulid');
    }
}
