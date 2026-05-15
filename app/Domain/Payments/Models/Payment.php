<?php

namespace App\Domain\Payments\Models;

use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Payments\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasFactory, HasUlids;

    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }

    protected $primaryKey = 'ulid';

    protected $fillable = [
        'visa_application_id',
        'status',
        'provider',
        'provider_payment_intent_id',
        'provider_checkout_session_id',
        'amount_subtotal',
        'amount_total',
        'currency',
        'failure_reason',
        'succeeded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount_subtotal' => 'integer',
            'amount_total' => 'integer',
            'succeeded_at' => 'datetime',
        ];
    }

    public function visaApplication(): BelongsTo
    {
        return $this->belongsTo(VisaApplication::class, 'visa_application_id', 'ulid');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PaymentItem::class, 'payment_id', 'ulid');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'payment_id', 'ulid');
    }
}
