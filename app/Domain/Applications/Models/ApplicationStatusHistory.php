<?php

namespace App\Domain\Applications\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationStatusHistory extends Model
{
    use HasUlids;

    protected $primaryKey = 'ulid';

    public $timestamps = false;

    protected $fillable = [
        'visa_application_id',
        'from_status',
        'to_status',
        'actor_id',
        'reason',
        'public_label',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function visaApplication(): BelongsTo
    {
        return $this->belongsTo(VisaApplication::class, 'visa_application_id', 'ulid');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
