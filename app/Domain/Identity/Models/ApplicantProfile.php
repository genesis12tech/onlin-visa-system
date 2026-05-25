<?php

namespace App\Domain\Identity\Models;

use App\Models\User;
use Database\Factories\ApplicantProfileFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantProfile extends Model
{
    /** @use HasFactory<ApplicantProfileFactory> */
    use HasFactory, HasUlids;

    protected static function newFactory(): ApplicantProfileFactory
    {
        return ApplicantProfileFactory::new();
    }

    protected $primaryKey = 'ulid';

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'middle_name',
        'date_of_birth',
        'gender',
        'nationality_id',
        'country_of_residence_id',
        'passport_number',
        'passport_expiry_date',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'notification_preferences',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'passport_expiry_date' => 'date',
            'passport_number' => 'encrypted',
            'phone' => 'encrypted',
            'notification_preferences' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function nationality(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'nationality_id');
    }

    public function countryOfResidence(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_of_residence_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
