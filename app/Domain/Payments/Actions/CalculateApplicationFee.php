<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Payments\Models\VisaFee;
use Illuminate\Support\Collection;

class CalculateApplicationFee
{
    /**
     * @return array{items: Collection, total_amount: int, currency: string}
     */
    public function execute(VisaApplication $application): array
    {
        $fees = VisaFee::where('visa_type_id', $application->visa_type_id)
            ->where('is_active', true)
            ->where('effective_from', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now());
            })
            ->get();

        if ($fees->isEmpty()) {
            throw new \RuntimeException('No active fees found for this visa type.');
        }

        $currencies = $fees->pluck('currency')->unique();
        if ($currencies->count() > 1) {
            throw new \RuntimeException('Mixed currencies are not supported for a single visa type.');
        }

        $items = $fees->map(fn (VisaFee $fee) => [
            'visa_fee_id' => $fee->ulid,
            'description' => $fee->name,
            'quantity' => 1,
            'unit_amount' => $fee->amount,
            'total_amount' => $fee->amount,
            'currency' => $fee->currency,
        ]);

        return [
            'items' => $items,
            'total_amount' => $items->sum('total_amount'),
            'currency' => $fees->first()->currency,
        ];
    }
}
