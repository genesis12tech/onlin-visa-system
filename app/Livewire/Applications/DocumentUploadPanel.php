<?php

namespace App\Livewire\Applications;

use App\Domain\Documents\Actions\UploadDocumentVersion;
use App\Domain\Documents\Enums\ScanStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class DocumentUploadPanel extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $applicationUlid;

    public mixed $pendingFile = null;

    public string $pendingDocumentUlid = '';

    public ?string $uploadError = null;

    public bool $pollingActive = false;

    public int $pollingStartedAt = 0;

    public bool $showCheckBackLater = false;

    public function mount(string $applicationUlid): void
    {
        $this->applicationUlid = $applicationUlid;
    }

    public function selectDocument(string $documentUlid): void
    {
        $document = ApplicationDocument::findOrFail($documentUlid);
        Gate::authorize('upload', $document);

        $this->pendingDocumentUlid = $documentUlid;
        $this->pendingFile = null;
        $this->uploadError = null;
    }

    public function updatedPendingFile(): void
    {
        if (! $this->pendingDocumentUlid || ! $this->pendingFile) {
            return;
        }

        $document = ApplicationDocument::findOrFail($this->pendingDocumentUlid);
        Gate::authorize('upload', $document);

        try {
            (new UploadDocumentVersion)->execute(
                $document,
                $this->pendingFile,
                auth()->user(),
            );

            $this->uploadError = null;
            $this->pollingActive = true;
            $this->pollingStartedAt = now()->unix();
            $this->showCheckBackLater = false;
        } catch (ValidationException $e) {
            $this->uploadError = collect($e->errors())->flatten()->first();
        }

        $this->pendingFile = null;
        $this->pendingDocumentUlid = '';
    }

    public function refreshDocuments(): void
    {
        if ($this->pollingStartedAt && now()->unix() - $this->pollingStartedAt > 300) {
            $this->pollingActive = false;
            $this->showCheckBackLater = true;

            return;
        }

        $hasPendingScans = ApplicationDocument::where('visa_application_id', $this->applicationUlid)
            ->whereHas('currentVersion', fn ($q) => $q->where('scan_status', ScanStatus::Pending->value))
            ->exists();

        if (! $hasPendingScans) {
            $this->pollingActive = false;
        }
    }

    public function render(): View
    {
        $documents = ApplicationDocument::where('visa_application_id', $this->applicationUlid)
            ->with(['documentType', 'currentVersion'])
            ->orderBy('created_at')
            ->get();

        $hasPendingScans = $documents->contains(
            fn ($doc) => $doc->currentVersion?->scan_status === ScanStatus::Pending
        );

        if ($this->pollingActive && ! $hasPendingScans) {
            $this->pollingActive = false;
        }

        $downloadUrls = $documents
            ->filter(fn ($doc) => $doc->currentVersion?->scan_status === ScanStatus::Clean)
            ->mapWithKeys(fn ($doc) => [
                $doc->ulid => URL::temporarySignedRoute(
                    'documents.download',
                    now()->addMinutes(15),
                    ['version' => $doc->currentVersion->ulid],
                ),
            ]);

        return view('livewire.applications.document-upload-panel', [
            'documents' => $documents,
            'hasPendingScans' => $hasPendingScans,
            'downloadUrls' => $downloadUrls,
        ]);
    }
}
