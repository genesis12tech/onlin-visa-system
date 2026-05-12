<?php

namespace Database\Seeders;

use App\Domain\Applications\Models\VisaType;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Documents\Models\VisaTypeDocumentRequirement;
use App\Domain\Identity\Models\Country;
use Illuminate\Database\Seeder;

class VisaTypeSeeder extends Seeder
{
    public function run(): void
    {
        $destination = Country::where('iso2', 'GB')->firstOrFail();

        $passport = DocumentType::where('name', 'Passport Biodata Page')->firstOrFail();
        $photo = DocumentType::where('name', 'Recent Passport Photo')->firstOrFail();
        $bank = DocumentType::where('name', 'Bank Statement')->firstOrFail();
        $flight = DocumentType::where('name', 'Flight Itinerary')->firstOrFail();
        $hotel = DocumentType::where('name', 'Hotel / Accommodation Reservation')->firstOrFail();
        $employment = DocumentType::where('name', 'Employment Letter')->firstOrFail();
        $insurance = DocumentType::where('name', 'Travel Insurance')->firstOrFail();
        $university = DocumentType::where('name', 'University Admission Letter')->firstOrFail();
        $business = DocumentType::where('name', 'Business Registration Certificate')->firstOrFail();
        $sponsor = DocumentType::where('name', 'Sponsor Letter')->firstOrFail();

        // Tourist Visa — first vertical slice
        $tourist = VisaType::updateOrCreate(['code' => 'TOURIST_30'], [
            'country_id' => $destination->id,
            'name' => 'Tourist Visa (30 Days)',
            'code' => 'TOURIST_30',
            'description' => 'Short-stay tourist visa for leisure, sightseeing, and visiting family or friends. Valid for 30 days from entry.',
            'processing_days' => 5,
            'validity_days' => 30,
            'max_entries' => 'single',
            'is_active' => true,
        ]);

        $this->attachDocuments($tourist, [
            [$passport, true, 1],
            [$photo, true, 2],
            [$bank, true, 3],
            [$flight, true, 4],
            [$hotel, true, 5],
            [$employment, false, 6],
            [$insurance, true, 7],
        ]);

        // Tourist Visa — 90 days, multiple entry
        $tourist90 = VisaType::updateOrCreate(['code' => 'TOURIST_90'], [
            'country_id' => $destination->id,
            'name' => 'Tourist Visa (90 Days)',
            'code' => 'TOURIST_90',
            'description' => 'Extended tourist visa allowing multiple entries within 90 days from first entry.',
            'processing_days' => 7,
            'validity_days' => 90,
            'max_entries' => 'multiple',
            'is_active' => true,
        ]);

        $this->attachDocuments($tourist90, [
            [$passport, true, 1],
            [$photo, true, 2],
            [$bank, true, 3],
            [$flight, true, 4],
            [$hotel, true, 5],
            [$employment, false, 6],
            [$insurance, true, 7],
        ]);

        // Business Visa
        $business_visa = VisaType::updateOrCreate(['code' => 'BUSINESS_90'], [
            'country_id' => $destination->id,
            'name' => 'Business Visa (90 Days)',
            'code' => 'BUSINESS_90',
            'description' => 'For attending meetings, conferences, or conducting business activities. Does not permit employment.',
            'processing_days' => 7,
            'validity_days' => 90,
            'max_entries' => 'multiple',
            'is_active' => true,
        ]);

        $this->attachDocuments($business_visa, [
            [$passport, true, 1],
            [$photo, true, 2],
            [$bank, true, 3],
            [$flight, true, 4],
            [$employment, true, 5],
            [$business, false, 6],
            [$insurance, true, 7],
        ]);

        // Student Visa
        $student = VisaType::updateOrCreate(['code' => 'STUDENT_365'], [
            'country_id' => $destination->id,
            'name' => 'Student Visa (1 Year)',
            'code' => 'STUDENT_365',
            'description' => 'For full-time study at an accredited institution. Renewable annually for the duration of the course.',
            'processing_days' => 15,
            'validity_days' => 365,
            'max_entries' => 'multiple',
            'is_active' => true,
        ]);

        $this->attachDocuments($student, [
            [$passport, true, 1],
            [$photo, true, 2],
            [$bank, true, 3],
            [$flight, true, 4],
            [$university, true, 5],
            [$sponsor, false, 6],
            [$insurance, true, 7],
        ]);
    }

    /**
     * @param  array<array{0: DocumentType, 1: bool, 2: int}>  $documents
     */
    private function attachDocuments(VisaType $visaType, array $documents): void
    {
        foreach ($documents as [$documentType, $isRequired, $order]) {
            VisaTypeDocumentRequirement::updateOrCreate(
                [
                    'visa_type_id' => $visaType->ulid,
                    'document_type_id' => $documentType->ulid,
                ],
                [
                    'is_required' => $isRequired,
                    'display_order' => $order,
                ]
            );
        }
    }
}
