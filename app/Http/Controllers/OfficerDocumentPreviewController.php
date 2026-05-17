<?php

namespace App\Http\Controllers;

use App\Domain\Documents\Enums\ScanStatus;
use App\Domain\Documents\Models\DocumentVersion;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfficerDocumentPreviewController extends Controller
{
    public function preview(Request $request, DocumentVersion $version): StreamedResponse
    {
        $document = $version->applicationDocument;

        Gate::authorize('view', $document);

        abort_if(
            $version->scan_status !== ScanStatus::Clean,
            403,
            'Document has not been cleared for preview.',
        );

        AuditLogger::log('document.previewed', $version, [
            'storage_path' => $version->storage_path,
        ], auth()->id());

        return Storage::disk('documents')->response(
            $version->storage_path,
            $version->original_filename,
            ['Content-Disposition' => 'inline; filename="'.$version->original_filename.'"'],
        );
    }
}
