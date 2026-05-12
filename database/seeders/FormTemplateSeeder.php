<?php

namespace Database\Seeders;

use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaType;
use Illuminate\Database\Seeder;

class FormTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $touristSchema = [
            'sections' => [
                [
                    'key' => 'travel_details',
                    'title' => 'Travel Details',
                    'fields' => [
                        [
                            'key' => 'purpose_of_visit',
                            'type' => 'select',
                            'label' => 'Purpose of Visit',
                            'required' => true,
                            'options' => ['Tourism', 'Visiting Family or Friends', 'Medical Treatment', 'Transit'],
                        ],
                        [
                            'key' => 'intended_arrival_date',
                            'type' => 'date',
                            'label' => 'Intended Arrival Date',
                            'required' => true,
                        ],
                        [
                            'key' => 'intended_departure_date',
                            'type' => 'date',
                            'label' => 'Intended Departure Date',
                            'required' => true,
                        ],
                        [
                            'key' => 'accommodation_name',
                            'type' => 'text',
                            'label' => 'Name of Accommodation',
                            'required' => true,
                            'placeholder' => 'Hotel name or host full name',
                        ],
                        [
                            'key' => 'accommodation_address',
                            'type' => 'textarea',
                            'label' => 'Accommodation Address',
                            'required' => true,
                        ],
                        [
                            'key' => 'number_of_entries',
                            'type' => 'select',
                            'label' => 'Number of Entries Required',
                            'required' => true,
                            'options' => ['Single', 'Multiple'],
                        ],
                    ],
                ],
                [
                    'key' => 'financial_information',
                    'title' => 'Financial Information',
                    'fields' => [
                        [
                            'key' => 'funds_available_usd',
                            'type' => 'number',
                            'label' => 'Funds Available for Trip (USD)',
                            'required' => true,
                            'min' => 0,
                        ],
                        [
                            'key' => 'financing_source',
                            'type' => 'select',
                            'label' => 'Source of Funding',
                            'required' => true,
                            'options' => ['Personal Savings', 'Employer', 'Sponsor', 'Family Member'],
                        ],
                    ],
                ],
                [
                    'key' => 'travel_history',
                    'title' => 'Travel History',
                    'fields' => [
                        [
                            'key' => 'previously_visited',
                            'type' => 'boolean',
                            'label' => 'Have you previously visited this country?',
                            'required' => true,
                        ],
                        [
                            'key' => 'previous_visit_details',
                            'type' => 'textarea',
                            'label' => 'If yes, provide details of previous visits',
                            'required' => false,
                            'conditional' => ['field' => 'previously_visited', 'value' => true],
                        ],
                        [
                            'key' => 'visa_refused',
                            'type' => 'boolean',
                            'label' => 'Have you ever been refused a visa or entry to any country?',
                            'required' => true,
                        ],
                        [
                            'key' => 'visa_refused_details',
                            'type' => 'textarea',
                            'label' => 'If yes, provide full details',
                            'required' => false,
                            'conditional' => ['field' => 'visa_refused', 'value' => true],
                        ],
                        [
                            'key' => 'criminal_conviction',
                            'type' => 'boolean',
                            'label' => 'Have you ever been convicted of a criminal offence in any country?',
                            'required' => true,
                        ],
                        [
                            'key' => 'criminal_conviction_details',
                            'type' => 'textarea',
                            'label' => 'If yes, provide details including country, offence, and sentence',
                            'required' => false,
                            'conditional' => ['field' => 'criminal_conviction', 'value' => true],
                        ],
                    ],
                ],
                [
                    'key' => 'emergency_contact',
                    'title' => 'Emergency Contact',
                    'fields' => [
                        [
                            'key' => 'emergency_contact_name',
                            'type' => 'text',
                            'label' => 'Full Name',
                            'required' => true,
                        ],
                        [
                            'key' => 'emergency_contact_phone',
                            'type' => 'text',
                            'label' => 'Phone Number (with country code)',
                            'required' => true,
                            'placeholder' => '+1 555 000 0000',
                        ],
                        [
                            'key' => 'emergency_contact_relationship',
                            'type' => 'select',
                            'label' => 'Relationship to Applicant',
                            'required' => true,
                            'options' => ['Spouse', 'Parent', 'Sibling', 'Child', 'Friend', 'Other'],
                        ],
                        [
                            'key' => 'emergency_contact_email',
                            'type' => 'email',
                            'label' => 'Email Address',
                            'required' => false,
                        ],
                    ],
                ],
            ],
        ];

        $businessSchema = [
            'sections' => [
                [
                    'key' => 'business_details',
                    'title' => 'Business Details',
                    'fields' => [
                        [
                            'key' => 'employer_name',
                            'type' => 'text',
                            'label' => 'Employer / Company Name',
                            'required' => true,
                        ],
                        [
                            'key' => 'job_title',
                            'type' => 'text',
                            'label' => 'Job Title',
                            'required' => true,
                        ],
                        [
                            'key' => 'business_purpose',
                            'type' => 'select',
                            'label' => 'Purpose of Business Visit',
                            'required' => true,
                            'options' => ['Meeting / Conference', 'Training', 'Contract Negotiation', 'Site Inspection', 'Trade Show', 'Other'],
                        ],
                        [
                            'key' => 'host_company_name',
                            'type' => 'text',
                            'label' => 'Name of Host Company in Destination',
                            'required' => true,
                        ],
                        [
                            'key' => 'host_company_address',
                            'type' => 'textarea',
                            'label' => 'Address of Host Company',
                            'required' => true,
                        ],
                        [
                            'key' => 'intended_arrival_date',
                            'type' => 'date',
                            'label' => 'Intended Arrival Date',
                            'required' => true,
                        ],
                        [
                            'key' => 'intended_departure_date',
                            'type' => 'date',
                            'label' => 'Intended Departure Date',
                            'required' => true,
                        ],
                    ],
                ],
                [
                    'key' => 'travel_history',
                    'title' => 'Travel & Visa History',
                    'fields' => [
                        [
                            'key' => 'visa_refused',
                            'type' => 'boolean',
                            'label' => 'Have you ever been refused a visa or entry to any country?',
                            'required' => true,
                        ],
                        [
                            'key' => 'visa_refused_details',
                            'type' => 'textarea',
                            'label' => 'If yes, provide full details',
                            'required' => false,
                            'conditional' => ['field' => 'visa_refused', 'value' => true],
                        ],
                    ],
                ],
                [
                    'key' => 'emergency_contact',
                    'title' => 'Emergency Contact',
                    'fields' => [
                        [
                            'key' => 'emergency_contact_name',
                            'type' => 'text',
                            'label' => 'Full Name',
                            'required' => true,
                        ],
                        [
                            'key' => 'emergency_contact_phone',
                            'type' => 'text',
                            'label' => 'Phone Number (with country code)',
                            'required' => true,
                        ],
                        [
                            'key' => 'emergency_contact_relationship',
                            'type' => 'select',
                            'label' => 'Relationship to Applicant',
                            'required' => true,
                            'options' => ['Spouse', 'Parent', 'Sibling', 'Child', 'Friend', 'Other'],
                        ],
                    ],
                ],
            ],
        ];

        foreach (VisaType::where('code', 'like', 'TOURIST%')->get() as $visaType) {
            FormTemplate::updateOrCreate(
                ['visa_type_id' => $visaType->ulid, 'version' => 1],
                [
                    'name' => $visaType->name.' — Application Form',
                    'schema' => $touristSchema,
                    'is_active' => true,
                    'published_at' => now(),
                ]
            );
        }

        $businessType = VisaType::where('code', 'BUSINESS_90')->first();
        if ($businessType) {
            FormTemplate::updateOrCreate(
                ['visa_type_id' => $businessType->ulid, 'version' => 1],
                [
                    'name' => 'Business Visa — Application Form',
                    'schema' => $businessSchema,
                    'is_active' => true,
                    'published_at' => now(),
                ]
            );
        }
    }
}
