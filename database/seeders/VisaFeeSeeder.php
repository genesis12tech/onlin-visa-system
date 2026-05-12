<?php

namespace Database\Seeders;

use App\Domain\Applications\Models\VisaType;
use App\Domain\Payments\Models\VisaFee;
use Illuminate\Database\Seeder;

class VisaFeeSeeder extends Seeder
{
    public function run(): void
    {
        $fees = [
            'TOURIST_30' => [
                ['name' => 'Application Fee', 'amount' => 5000, 'applicant_type' => 'all'],
                ['name' => 'Processing Fee', 'amount' => 1500, 'applicant_type' => 'all'],
            ],
            'TOURIST_90' => [
                ['name' => 'Application Fee', 'amount' => 10000, 'applicant_type' => 'all'],
                ['name' => 'Processing Fee', 'amount' => 2000, 'applicant_type' => 'all'],
            ],
            'BUSINESS_90' => [
                ['name' => 'Application Fee', 'amount' => 15000, 'applicant_type' => 'all'],
                ['name' => 'Processing Fee', 'amount' => 2500, 'applicant_type' => 'all'],
            ],
            'STUDENT_365' => [
                ['name' => 'Application Fee', 'amount' => 12000, 'applicant_type' => 'all'],
                ['name' => 'Processing Fee', 'amount' => 2000, 'applicant_type' => 'all'],
                ['name' => 'Healthcare Surcharge', 'amount' => 47000, 'applicant_type' => 'all'],
            ],
        ];

        foreach ($fees as $visaCode => $visaFees) {
            $visaType = VisaType::where('code', $visaCode)->first();

            if (! $visaType) {
                continue;
            }

            foreach ($visaFees as $fee) {
                VisaFee::updateOrCreate(
                    [
                        'visa_type_id' => $visaType->ulid,
                        'name' => $fee['name'],
                        'applicant_type' => $fee['applicant_type'],
                    ],
                    [
                        'amount' => $fee['amount'], // in cents: 5000 = $50.00 USD
                        'currency' => 'USD',
                        'effective_from' => now()->startOfYear(),
                        'effective_to' => null,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
