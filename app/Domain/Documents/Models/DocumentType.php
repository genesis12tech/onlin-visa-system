<?php

namespace App\Domain\Documents\Models;

use Database\Factories\DocumentTypeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    use HasFactory, HasUlids;

    protected static function newFactory(): DocumentTypeFactory
    {
        return DocumentTypeFactory::new();
    }

    protected $primaryKey = 'ulid';

    protected $fillable = [
        'name',
        'description',
        'accepted_mime_types',
        'max_size_kb',
        'max_pages',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'accepted_mime_types' => 'array',
            'max_size_kb' => 'integer',
            'max_pages' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(VisaTypeDocumentRequirement::class, 'document_type_id', 'ulid');
    }
}
