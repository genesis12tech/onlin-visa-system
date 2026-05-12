<?php

namespace Database\Seeders;

use App\Domain\Documents\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Passport Biodata Page',
                'description' => 'A clear scan or photo of the main passport page showing your photo, personal details, and passport number.',
                'accepted_mime_types' => ['image/jpeg', 'image/png', 'application/pdf'],
                'max_size_kb' => 5120,
                'max_pages' => 1,
            ],
            [
                'name' => 'Recent Passport Photo',
                'description' => 'A recent passport-style photograph taken within the last 6 months. White or light background, no glasses.',
                'accepted_mime_types' => ['image/jpeg', 'image/png'],
                'max_size_kb' => 2048,
                'max_pages' => null,
            ],
            [
                'name' => 'Bank Statement',
                'description' => 'Last 3 months\' bank statements showing sufficient funds for the duration of your stay.',
                'accepted_mime_types' => ['application/pdf'],
                'max_size_kb' => 10240,
                'max_pages' => 10,
            ],
            [
                'name' => 'Flight Itinerary',
                'description' => 'Confirmed or tentative flight booking showing entry and exit dates.',
                'accepted_mime_types' => ['application/pdf', 'image/jpeg', 'image/png'],
                'max_size_kb' => 5120,
                'max_pages' => 5,
            ],
            [
                'name' => 'Hotel / Accommodation Reservation',
                'description' => 'Confirmed hotel booking or a letter of invitation from a host.',
                'accepted_mime_types' => ['application/pdf', 'image/jpeg', 'image/png'],
                'max_size_kb' => 5120,
                'max_pages' => 5,
            ],
            [
                'name' => 'Employment Letter',
                'description' => 'A letter from your employer on official letterhead confirming your employment, salary, and approved leave.',
                'accepted_mime_types' => ['application/pdf'],
                'max_size_kb' => 5120,
                'max_pages' => 3,
            ],
            [
                'name' => 'Business Registration Certificate',
                'description' => 'For self-employed applicants: official business registration document.',
                'accepted_mime_types' => ['application/pdf', 'image/jpeg', 'image/png'],
                'max_size_kb' => 5120,
                'max_pages' => 5,
            ],
            [
                'name' => 'University Admission Letter',
                'description' => 'Official letter of acceptance from the educational institution.',
                'accepted_mime_types' => ['application/pdf'],
                'max_size_kb' => 5120,
                'max_pages' => 5,
            ],
            [
                'name' => 'Sponsor Letter',
                'description' => 'Letter from your sponsor confirming financial responsibility for your trip.',
                'accepted_mime_types' => ['application/pdf'],
                'max_size_kb' => 5120,
                'max_pages' => 3,
            ],
            [
                'name' => 'Travel Insurance',
                'description' => 'Proof of travel insurance covering the full duration of your stay with minimum coverage of $30,000 USD.',
                'accepted_mime_types' => ['application/pdf'],
                'max_size_kb' => 5120,
                'max_pages' => 5,
            ],
        ];

        foreach ($types as $type) {
            DocumentType::updateOrCreate(['name' => $type['name']], array_merge($type, ['is_active' => true]));
        }
    }
}
