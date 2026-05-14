<?php

namespace App\Domain\Applications\Models;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class VisaApplication extends Model
{
    use HasUlids, LogsActivity;

    protected $primaryKey = 'ulid';

    protected $fillable = [
        'tracking_number',
        'applicant_profile_id',
        'visa_type_id',
        'form_template_id',
        'status',
        'assigned_officer_id',
        'submitted_at',
        'travel_date',
        'decision_at',
        'decision_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'submitted_at' => 'datetime',
            'travel_date' => 'date',
            'decision_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'assigned_officer_id', 'decision_reason'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function applicantProfile(): BelongsTo
    {
        return $this->belongsTo(ApplicantProfile::class, 'applicant_profile_id', 'ulid');
    }

    public function visaType(): BelongsTo
    {
        return $this->belongsTo(VisaType::class, 'visa_type_id', 'ulid');
    }

    public function formTemplate(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class, 'form_template_id', 'ulid');
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_officer_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ApplicationStatusHistory::class, 'visa_application_id', 'ulid');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class, 'visa_application_id', 'ulid');
    }
}
