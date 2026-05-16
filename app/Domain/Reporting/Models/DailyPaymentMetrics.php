<?php

namespace App\Domain\Reporting\Models;

use Illuminate\Database\Eloquent\Model;

class DailyPaymentMetrics extends Model
{
    protected $fillable = [
        'date',
        'currency',
        'total_collected',
        'total_refunded',
        'succeeded_count',
        'failed_count',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'total_collected' => 'integer',
            'total_refunded' => 'integer',
            'succeeded_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }
}
