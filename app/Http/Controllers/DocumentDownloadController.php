<?php

namespace App\Http\Controllers;

use App\Domain\Documents\Enums\ScanStatus;
use App\Domain\Documents\Models\DocumentVersion;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController extends Controller
{
    public function download(Request $request, DocumentVersion $version): StreamedResponse
    {
        $document = $version->applicationDocument;

        Gate::authorize('view', $document);

        abort_if(
            $version->scan_status !== ScanStatus::Clean,
            403,
            'Document is not cleared for download.',
        );

        AuditLogger::log('document.downloaded', $version, [
            'storage_path' => $version->storage_path,
        ], auth()->id());

        return Storage::disk('documents')->download(
            $version->storage_path,
            $version->original_filename,
        );
    }
}
