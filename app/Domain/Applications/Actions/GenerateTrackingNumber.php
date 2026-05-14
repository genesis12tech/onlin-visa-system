<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Support\Str;

class GenerateTrackingNumber
{
    public function execute(): string
    {
        do {
            $reference = 'VA-'.now()->year.'-'.strtoupper(Str::random(6));
        } while (VisaApplication::where('tracking_number', $reference)->exists());

        return $reference;
    }
}
