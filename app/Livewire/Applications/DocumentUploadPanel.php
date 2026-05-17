<?php

namespace App\Livewire\Applications;

use App\Domain\Documents\Models\ApplicationDocument;
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

    public function render(): View
    {
        $documents = ApplicationDocument::where('visa_application_id', $this->applicationUlid)
            ->with(['documentType', 'currentVersion'])
            ->orderBy('created_at')
            ->get();

        return view('livewire.applications.document-upload-panel', [
            'documents' => $documents,
        ]);
    }
}
