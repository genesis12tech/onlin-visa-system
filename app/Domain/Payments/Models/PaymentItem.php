<?php

namespace App\Domain\Payments\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentItem extends Model
{
    use HasUlids;

    protected $primaryKey = 'ulid';

    protected $fillable = [
        'payment_id',
        'visa_fee_id',
        'description',
        'quantity',
        'unit_amount',
        'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount' => 'integer',
            'total_amount' => 'integer',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'ulid');
    }
}
