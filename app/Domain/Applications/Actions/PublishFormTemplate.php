<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Models\FormTemplate;
use Illuminate\Support\Facades\DB;

final class PublishFormTemplate
{
    public function execute(FormTemplate $template): void
    {
        if ($template->published_at !== null) {
            throw new \RuntimeException('This form template is already published.');
        }

        DB::transaction(function () use ($template): void {
            // Archive the previous active version for this visa type
            FormTemplate::where('visa_type_id', $template->visa_type_id)
                ->where('ulid', '!=', $template->ulid)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $template->update([
                'published_at' => now(),
                'is_active' => true,
            ]);
        });
    }
}
