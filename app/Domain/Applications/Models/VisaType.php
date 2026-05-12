<?php

namespace App\Domain\Applications\Models;

use App\Domain\Identity\Models\Country;
use App\Domain\Payments\Models\VisaFee;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisaType extends Model
{
    use HasUlids;

    protected $primaryKey = 'ulid';

    protected $fillable = [
        'country_id',
        'name',
        'code',
        'description',
        'processing_days',
        'validity_days',
        'max_entries',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'processing_days' => 'integer',
            'validity_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function fees(): HasMany
    {
        return $this->hasMany(VisaFee::class, 'visa_type_id', 'ulid');
    }

    public function formTemplates(): HasMany
    {
        return $this->hasMany(FormTemplate::class, 'visa_type_id', 'ulid');
    }
}
