<?php

namespace App\Domain\Documents\Models;

use App\Domain\Applications\Models\VisaType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisaTypeDocumentRequirement extends Model
{
    use HasUlids;

    protected $primaryKey = 'ulid';

    protected $fillable = [
        'visa_type_id',
        'document_type_id',
        'is_required',
        'display_order',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function visaType(): BelongsTo
    {
        return $this->belongsTo(VisaType::class, 'visa_type_id', 'ulid');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id', 'ulid');
    }
}
