<?php

namespace App\Http\Controllers;

use App\Domain\Reporting\Models\ApplicationExport;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ExportDownloadController extends Controller
{
    public function download(Request $request, string $ulid)
    {
        $export = ApplicationExport::where('ulid', $ulid)->firstOrFail();

        Gate::authorize('download', $export);

        AuditLogger::log('export.downloaded', $export, ['ulid' => $export->ulid]);

        return Storage::disk('local')->download(
            $export->file_path,
            'applications-export-'.$export->ulid.'.csv',
            ['Content-Type' => 'text/csv'],
        );
    }
}
