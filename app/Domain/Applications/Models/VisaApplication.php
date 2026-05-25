<?php

namespace App\Domain\Applications\Models;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use Database\Factories\VisaApplicationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class VisaApplication extends Model
{
    /** @use HasFactory<VisaApplicationFactory> */
    use HasFactory, HasUlids, LogsActivity;

    protected static function newFactory(): VisaApplicationFactory
    {
        return VisaApplicationFactory::new();
    }

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
        'validity_period',
        'entry_type',
        'decision_letter_pdf_path',
        'summary_pdf_path',
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'visa_application_id', 'ulid');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ApplicationAnswer::class, 'visa_application_id', 'ulid');
    }

    public function snapshot(): HasOne
    {
        return $this->hasOne(ApplicationSnapshot::class, 'visa_application_id', 'ulid');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ApplicationNote::class, 'visa_application_id', 'ulid')
            ->latest();
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(ApplicationAppointment::class, 'visa_application_id', 'ulid')
            ->latest();
    }

    public function latestAppointment(): HasOne
    {
        return $this->hasOne(ApplicationAppointment::class, 'visa_application_id', 'ulid')
            ->latestOfMany('appointment_at');
    }

    public function scopeSlaBreached(Builder $query): Builder
    {
        return $this->applySlaScope(
            $query,
            isSqlite: DB::connection()->getDriverName() === 'sqlite',
            condition: 'lt',
        );
    }

    public function scopeSlaAtRisk(Builder $query): Builder
    {
        return $this->applySlaScope(
            $query,
            isSqlite: DB::connection()->getDriverName() === 'sqlite',
            condition: 'between',
        );
    }

    private function applySlaScope(Builder $query, bool $isSqlite, string $condition): Builder
    {
        $q = $query
            ->select('visa_applications.*')
            ->whereNull('visa_applications.decision_at')
            ->whereNotNull('visa_applications.submitted_at')
            ->join('visa_types', 'visa_applications.visa_type_id', '=', 'visa_types.ulid');

        if ($isSqlite) {
            $deadlineExpr = "datetime(visa_applications.submitted_at, '+' || visa_types.processing_days || ' days')";
            $nowExpr = "datetime('now')";
            $twoFromNow = "datetime('now', '+2 days')";
        } else {
            $deadlineExpr = 'DATE_ADD(visa_applications.submitted_at, INTERVAL visa_types.processing_days DAY)';
            $nowExpr = 'NOW()';
            $twoFromNow = 'DATE_ADD(NOW(), INTERVAL 2 DAY)';
        }

        if ($condition === 'lt') {
            $q->whereRaw("{$deadlineExpr} < {$nowExpr}");
        } else {
            $q->whereRaw("{$deadlineExpr} BETWEEN {$nowExpr} AND {$twoFromNow}");
        }

        return $q;
    }

    public function acceptedDocumentsCount(): int
    {
        return $this->documents
            ->filter(fn (ApplicationDocument $d) => $d->status === DocumentStatus::Accepted)
            ->count();
    }

    public function acceptedDocCount(): int
    {
        return $this->acceptedDocumentsCount();
    }

    public function formattedFee(): string
    {
        $fee = $this->visaType?->fees()
            ->where('is_active', true)
            ->where('applicant_type', 'all')
            ->whereDate('effective_from', '<=', now())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', now()))
            ->first();

        if (! $fee) {
            return '—';
        }

        return number_format($fee->amount / 100, 2) . ' ' . $fee->currency;
    }

    public function requiredDocumentsCount(): int
    {
        return $this->visaType
            ->documentRequirements()
            ->where('is_required', true)
            ->count();
    }

    public function workflowProgressPercent(): int
    {
        $positions = [
            'draft' => 5,
            'submitted' => 20,
            'payment_pending' => 25,
            'payment_completed' => 35,
            'under_review' => 55,
            'additional_info_requested' => 55,
            'docs_required' => 60,
            'approved' => 100,
            'rejected' => 100,
            'withdrawn' => 100,
        ];

        return $positions[$this->status->value] ?? 0;
    }

    public function workflowProgressColour(): string
    {
        return match ($this->status) {
            ApplicationStatus::Approved => 'bg-green-500',
            ApplicationStatus::Rejected => 'bg-red-500',
            ApplicationStatus::AdditionalInfoRequested => 'bg-amber-500',
            default => 'bg-blue-500',
        };
    }

    public function workflowProgressLabel(): string
    {
        return match ($this->status) {
            ApplicationStatus::Draft => 'Draft',
            ApplicationStatus::Submitted,
            ApplicationStatus::PaymentPending => 'Payment pending',
            ApplicationStatus::PaymentCompleted => 'Paid',
            ApplicationStatus::UnderReview => 'Under review',
            ApplicationStatus::AdditionalInfoRequested => 'Action required',
            ApplicationStatus::DocsRequired => 'Documents required',
            ApplicationStatus::Approved => 'Complete',
            ApplicationStatus::Rejected => 'Complete',
            ApplicationStatus::Withdrawn => 'Withdrawn',
        };
    }

    public function latestRejectedDocumentName(): ?string
    {
        $doc = $this->documents
            ->filter(fn (ApplicationDocument $d) => $d->status === DocumentStatus::Rejected)
            ->sortByDesc('updated_at')
            ->first();

        return $doc?->documentType?->name;
    }

    public function getSlaRemainingDaysAttribute(): int
    {
        if ($this->decision_at || ! $this->submitted_at) {
            return 0;
        }

        $processingDays = $this->visaType?->processing_days ?? 30;
        $deadline = $this->submitted_at->copy()->addDays($processingDays);

        return (int) now()->diffInDays($deadline, false);
    }
}
