<?php

namespace Database\Factories;

use App\Domain\Documents\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentType>
 */
class DocumentTypeFactory extends Factory
{
    protected $model = DocumentType::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->words(2, true)),
            'description' => $this->faker->sentence(),
            'accepted_mime_types' => ['application/pdf', 'image/jpeg', 'image/png'],
            'max_size_kb' => 5120,
            'max_pages' => null,
            'is_active' => true,
        ];
    }

    public function pdfOnly(): static
    {
        return $this->state([
            'accepted_mime_types' => ['application/pdf'],
            'max_pages' => 10,
        ]);
    }
}
