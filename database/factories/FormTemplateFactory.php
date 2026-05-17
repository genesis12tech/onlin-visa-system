<?php

namespace Database\Factories;

use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormTemplate>
 */
class FormTemplateFactory extends Factory
{
    protected $model = FormTemplate::class;

    public function definition(): array
    {
        return [
            'visa_type_id' => VisaType::factory(),
            'name' => 'Standard Application Form',
            'version' => 1,
            'schema' => [
                'sections' => [
                    [
                        'key' => 'travel_details',
                        'title' => 'Travel Details',
                        'fields' => [
                            [
                                'key' => 'travel_purpose',
                                'type' => 'select',
                                'label' => 'Purpose of travel',
                                'required' => true,
                                'options' => ['Tourism', 'Business', 'Study', 'Medical'],
                            ],
                            [
                                'key' => 'intended_entry_date',
                                'type' => 'date',
                                'label' => 'Intended entry date',
                                'required' => true,
                            ],
                            [
                                'key' => 'intended_stay_days',
                                'type' => 'text',
                                'label' => 'Intended length of stay (days)',
                                'required' => true,
                            ],
                        ],
                    ],
                    [
                        'key' => 'background',
                        'title' => 'Background',
                        'fields' => [
                            [
                                'key' => 'previous_visa_refusal',
                                'type' => 'radio',
                                'label' => 'Have you ever been refused a visa?',
                                'required' => true,
                                'options' => ['yes', 'no'],
                            ],
                        ],
                    ],
                ],
            ],
            'is_active' => true,
            'published_at' => now(),
        ];
    }
}
