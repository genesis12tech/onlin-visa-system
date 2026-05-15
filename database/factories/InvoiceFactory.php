<?php

namespace Database\Factories;

use App\Domain\Payments\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'payment_id' => fake()->ulid(),
            'invoice_number' => 'INV-'.date('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'issued_at' => now(),
            'pdf_storage_path' => null,
        ];
    }
}
