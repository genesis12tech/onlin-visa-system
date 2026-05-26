<?php

namespace App\Domain\Identity\Models;

use App\Models\User;
use Database\Factories\OfficerProfileFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OfficerProfile extends Model
{
    /** @use HasFactory<OfficerProfileFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected static function newFactory(): OfficerProfileFactory
    {
        return OfficerProfileFactory::new();
    }

    protected $primaryKey = 'ulid';

    protected $fillable = [
        'user_id',
        'display_initials',
        'capacity',
        'specialisations',
        'avatar_color',
        'is_accepting_assignments',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'specialisations' => 'array',
            'is_accepting_assignments' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
