<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Models\ApplicationAnswer;
use App\Domain\Applications\Models\VisaApplication;

class UpdateApplicationSection
{
    public static function run(VisaApplication $application, string $sectionKey, array $fieldAnswers): void
    {
        $visibleKeys = collect(array_keys($fieldAnswers))
            ->map(fn (string $k) => "{$sectionKey}.{$k}")
            ->all();

        ApplicationAnswer::where('visa_application_id', $application->ulid)
            ->where('field_key', 'like', "{$sectionKey}.%")
            ->whereNotIn('field_key', $visibleKeys)
            ->delete();

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
