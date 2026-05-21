<?php

namespace App\Livewire\Documents;

use App\Domain\Documents\Models\ApplicationDocument;
use Illuminate\View\View;
use Livewire\Component;

class DocumentsPage extends Component
{
    public array $documentsByApplication = [];

    public function mount(): void
    {
        $profile = auth()->user()->applicantProfile;

        if (! $profile) {
            return;
        }

        $documents = ApplicationDocument::whereHas(
            'visaApplication',
            fn ($q) => $q->where('applicant_profile_id', $profile->ulid)
        )
            ->with([
                'visaApplication',
                'visaApplication.visaType',
                'visaApplication.visaType.country',
                'documentType',
            ])
            ->orderBy('created_at')
            ->get();

        $this->documentsByApplication = $documents
            ->groupBy('visa_application_id')
            ->map(fn ($docs) => [
                'application' => [
                    'ulid' => $docs->first()->visaApplication->ulid,
                    'tracking_number' => $docs->first()->visaApplication->tracking_number,
                    'visa_type_name' => $docs->first()->visaApplication->visaType->name,
                    'country_name' => $docs->first()->visaApplication->visaType->country->name,
                ],
                'documents' => $docs->map(fn ($doc) => [
                    'type_name' => $doc->documentType->name,
                    'status' => $doc->status,
                    'updated_at' => $doc->updated_at->format('d M Y'),
                ])->all(),
            ])
            ->values()
            ->all();
    }

    public function render(): View
    {
        return view('livewire.documents.documents-page')
            ->layout('layouts.app');
    }
}
