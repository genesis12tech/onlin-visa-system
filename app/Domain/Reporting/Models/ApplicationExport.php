<?php

namespace App\Domain\Reporting\Models;

use App\Domain\Reporting\Enums\ExportStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationExport extends Model
{
    use HasUlids;

    protected $primaryKey = 'ulid';

    protected $fillable = [
        'requested_by',
        'filters',
        'status',
        'file_path',
        'row_count',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'status' => ExportStatus::class,
            'row_count' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by', 'id');
    }
}
