<?php

namespace App\Livewire\Applications;

use App\Domain\Applications\Actions\SubmitInfoResponse;
use App\Domain\Applications\Models\ApplicationAnswer;
use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class InfoResponsePanel extends Component
{
    #[Locked]
    public string $applicationUlid;

    /** @var array<string, array<string, mixed>> keyed by section_key => [field_key => value] */
    public array $answers = [];

    public function mount(string $applicationUlid): void
    {
        $this->applicationUlid = $applicationUlid;

        $application = VisaApplication::with(['answers', 'notes'])
            ->where('ulid', $applicationUlid)
            ->firstOrFail();

        foreach ($this->resolveFieldsToUnlock($application) as $fullKey) {
            [$section, $field] = explode('.', $fullKey, 2);
            $answer = $application->answers->firstWhere('field_key', $fullKey);
            $this->answers[$section][$field] = $answer?->value;
        }
    }

    public function updatedAnswers(string $key): void
    {
        $parts = explode('.', $key, 2);

        if (count($parts) < 2) {
            return;
        }

        [$section, $field] = $parts;
        $this->saveField($section, $field);
    }

    public function saveField(string $sectionKey, string $fieldKey): void
    {
        $application = VisaApplication::where('ulid', $this->applicationUlid)->firstOrFail();
        Gate::authorize('respondToInfoRequest', $application);

        $value = $this->answers[$sectionKey][$fieldKey] ?? null;

        if ($value === null || $value === '') {
            ApplicationAnswer::where('visa_application_id', $application->ulid)
                ->where('field_key', "{$sectionKey}.{$fieldKey}")
                ->delete();

            return;
        }

        ApplicationAnswer::updateOrCreate(
            [
                'visa_application_id' => $application->ulid,
                'field_key' => "{$sectionKey}.{$fieldKey}",
            ],
            ['value' => $value],
        );
    }

    public function submitResponse(): void
    {
        $application = VisaApplication::where('ulid', $this->applicationUlid)->firstOrFail();
        Gate::authorize('respondToInfoRequest', $application);

        try {
            app(SubmitInfoResponse::class)->execute($application, auth()->user());
        } catch (\RuntimeException $e) {
            $this->addError('submit', $e->getMessage());

            return;
        }

        $this->redirect(route('applications.wizard', $application->tracking_number));
    }

    public function render(): View
    {
        $application = VisaApplication::with(['formTemplate', 'notes', 'documents'])
            ->where('ulid', $this->applicationUlid)
            ->firstOrFail();

        $infoNote = $application->notes
            ->where('is_visible_to_applicant', true)
            ->whereNotNull('metadata')
            ->first();

        $fieldsToUnlock = $infoNote?->metadata['fields_to_unlock'] ?? [];

        $sections = collect($application->formTemplate->schema['sections'] ?? [])
            ->map(function (array $section) use ($fieldsToUnlock): array {
                $sectionKey = $section['key'];
                $unlockedFields = collect($section['fields'])
                    ->filter(fn (array $f) => in_array("{$sectionKey}.{$f['key']}", $fieldsToUnlock))
                    ->values()
                    ->all();

                return array_merge($section, ['fields' => $unlockedFields]);
            })
            ->filter(fn (array $s) => count($s['fields']) > 0)
            ->values()
            ->all();

        return view('livewire.applications.info-response-panel', [
            'application' => $application,
            'infoNote' => $infoNote,
            'sections' => $sections,
        ]);
    }

    /** @return list<string> */
    private function resolveFieldsToUnlock(VisaApplication $application): array
    {
        $note = $application->notes
            ->where('is_visible_to_applicant', true)
            ->whereNotNull('metadata')
            ->first();

        return $note?->metadata['fields_to_unlock'] ?? [];
    }
}
