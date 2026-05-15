<?php

namespace App\Domain\Payments\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $primaryKey = 'ulid';

    protected $fillable = [
        'provider',
        'event_id',
        'event_type',
        'payload',
        'processed_at',
        'processing_error',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function markProcessed(): void
    {
        $this->update(['processed_at' => now()]);
    }

    public function markFailed(string $error): void
    {
        $this->update(['processing_error' => $error]);
    }
}
