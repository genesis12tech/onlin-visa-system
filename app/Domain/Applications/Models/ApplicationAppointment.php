<?php

namespace App\Domain\Applications\Models;

use App\Domain\Applications\Enums\AppointmentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationAppointment extends Model
{
    use HasUlids;

    protected $primaryKey = 'ulid';

    protected $fillable = [
        'visa_application_id',
        'created_by',
        'appointment_at',
        'location',
        'status',
        'instructions',
        'confirmation_pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'appointment_at' => 'datetime',
            'status' => AppointmentStatus::class,
        ];
    }

    public function visaApplication(): BelongsTo
    {
        return $this->belongsTo(VisaApplication::class, 'visa_application_id', 'ulid');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
