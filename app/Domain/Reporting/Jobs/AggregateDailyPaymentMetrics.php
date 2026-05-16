<?php

namespace App\Domain\Reporting\Jobs;

use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Reporting\Models\DailyPaymentMetrics;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class AggregateDailyPaymentMetrics implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $backoff = 30;

    public function __construct(
        public readonly string $date, // Y-m-d
    ) {
        $this->onQueue('reports');
    }

    public function handle(): void
    {
        $date = Carbon::parse($this->date);
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $currencies = Payment::whereBetween('created_at', [$start, $end])
            ->distinct()
            ->pluck('currency');

        foreach ($currencies as $currency) {
            $payments = Payment::where('currency', $currency)
                ->whereBetween('created_at', [$start, $end])
                ->get(['status', 'amount_total']);

            $succeeded = $payments->filter(fn ($p) => $p->status === PaymentStatus::Succeeded);
            $failed = $payments->filter(fn ($p) => $p->status === PaymentStatus::Failed);

            DailyPaymentMetrics::updateOrCreate(
                ['date' => $date->copy()->startOfDay(), 'currency' => $currency],
                [
                    'total_collected' => (int) $succeeded->sum('amount_total'),
                    'total_refunded' => 0,
                    'succeeded_count' => $succeeded->count(),
                    'failed_count' => $failed->count(),
                ]
            );
        }
    }
}
