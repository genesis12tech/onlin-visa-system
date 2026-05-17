<?php

namespace App\Livewire\Applications;

use App\Domain\Applications\Actions\UpdateApplicationSection;
use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DynamicFormSection extends Component
{
    #[Locked]
    public string $applicationUlid;

    /** @var array<string, mixed> */
    #[Locked]
    public array $section = [];

    /** @var array<string, mixed> keyed by bare field_key */
    public array $answers = [];

    public bool $saved = false;

    /** @param array<string, mixed> $savedAnswers */
    public function mount(string $applicationUlid, array $section, array $savedAnswers = []): void
    {
        $this->applicationUlid = $applicationUlid;
        $this->section = $section;

        foreach ($section['fields'] as $field) {
            $this->answers[$field['key']] = $savedAnswers[$field['key']] ?? null;
        }
    }

    public function updatedAnswers(string $key): void
    {
        $this->autoSave();
    }

    public function autoSave(): void
    {
        $this->saved = false;

        $application = VisaApplication::where('ulid', $this->applicationUlid)->firstOrFail();
        Gate::authorize('update', $application);

        $visibleAnswers = [];
        foreach ($this->section['fields'] as $field) {
            if ($this->isFieldVisible($field)) {
                $visibleAnswers[$field['key']] = $this->answers[$field['key']] ?? null;
            }
        }

        UpdateApplicationSection::run($application, $this->section['key'], $visibleAnswers);

        $this->saved = true;

        $this->dispatch('section-updated', sectionKey: $this->section['key'])->to(ApplicationWizard::class);
    }

    /** @return array<string, bool> */
    #[Computed]
    public function visibleFields(): array
    {
        $result = [];
        foreach ($this->section['fields'] as $field) {
            $result[$field['key']] = $this->isFieldVisible($field);
        }

        return $result;
    }

    /** @param array<string, mixed> $field */
    private function isFieldVisible(array $field): bool
    {
        if (! isset($field['condition'])) {
            return true;
        }

        $conditionField = $field['condition']['field'] ?? null;
        $conditionValue = $field['condition']['equals'] ?? null;

        if ($conditionField === null) {
            return true;
        }

        return ($this->answers[$conditionField] ?? null) === $conditionValue;
    }

    public function render(): View
    {
        return view('livewire.applications.dynamic-form-section', [
            'visibleFields' => $this->visibleFields(),
        ]);
    }
}
