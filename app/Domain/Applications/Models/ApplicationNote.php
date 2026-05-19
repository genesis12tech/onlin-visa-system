<?php

namespace App\Domain\Applications\Models;

use App\Models\User;
use Database\Factories\ApplicationNoteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationNote extends Model
{
    /** @use HasFactory<ApplicationNoteFactory> */
    use HasFactory, HasUlids;

    protected static function newFactory(): ApplicationNoteFactory
    {
        return ApplicationNoteFactory::new();
    }

    protected $primaryKey = 'ulid';

    protected $fillable = [
        'visa_application_id',
        'author_id',
        'body',
        'is_visible_to_applicant',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_visible_to_applicant' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function visaApplication(): BelongsTo
    {
        return $this->belongsTo(VisaApplication::class, 'visa_application_id', 'ulid');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopeVisibleToApplicant(Builder $query): Builder
    {
        return $query->where('is_visible_to_applicant', true);
    }
}
