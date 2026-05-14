<?php

namespace App\Domain\Documents\Models;

use App\Domain\Documents\Enums\ScanStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    use HasUlids;

    protected $primaryKey = 'ulid';

    public $timestamps = false;

    protected $fillable = [
        'application_document_id',
        'storage_path',
        'original_filename',
        'mime_type',
        'file_size_bytes',
        'sha256_checksum',
        'scan_status',
        'scan_completed_at',
        'uploaded_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'scan_status' => ScanStatus::class,
            'scan_completed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function applicationDocument(): BelongsTo
    {
        return $this->belongsTo(ApplicationDocument::class, 'application_document_id', 'ulid');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
