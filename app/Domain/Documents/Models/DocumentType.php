<?php

namespace App\Domain\Documents\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    use HasUlids;

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
}
