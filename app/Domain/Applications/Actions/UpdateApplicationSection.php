<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Models\ApplicationAnswer;
use App\Domain\Applications\Models\VisaApplication;

class UpdateApplicationSection
{
    public static function run(VisaApplication $application, string $sectionKey, array $fieldAnswers): void
    {
        foreach ($fieldAnswers as $fieldKey => $value) {
            if ($value === null) {
                continue;
            }

            ApplicationAnswer::updateOrCreate(
                [
                    'visa_application_id' => $application->ulid,
                    'field_key' => "{$sectionKey}.{$fieldKey}",
                ],
                ['value' => $value],
            );
        }
    }
}
