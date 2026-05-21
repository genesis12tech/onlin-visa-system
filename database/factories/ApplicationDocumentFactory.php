<?php

namespace Database\Factories;

use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationDocument>
 */
class ApplicationDocumentFactory extends Factory
{
    protected $model = ApplicationDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'visa_application_id' => VisaApplication::factory(),
            'document_type_id' => DocumentType::factory(),
            'current_version_id' => null,
            'status' => DocumentStatus::Pending,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ];
    }

    public function uploaded(): static
    {
        return $this->state(['status' => DocumentStatus::Uploaded]);
    }

    public function accepted(): static
    {
        return $this->state(['status' => DocumentStatus::Accepted]);
    }

    public function rejected(): static
    {
        return $this->state(['status' => DocumentStatus::Rejected]);
    }
}
