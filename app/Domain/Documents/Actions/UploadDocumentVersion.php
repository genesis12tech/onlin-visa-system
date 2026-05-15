<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\ScanStatus;
use App\Domain\Documents\Jobs\ScanDocumentVersionJob;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Documents\Models\DocumentVersion;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UploadDocumentVersion
{
    public function execute(
        ApplicationDocument $document,
        UploadedFile $file,
        User $uploader,
    ): DocumentVersion {
        $this->validateFile($file, $document->documentType);

        $contents = file_get_contents($file->getRealPath());
        $sha256 = hash('sha256', $contents);

        $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $storagePath = 'documents/'.Str::ulid().'.'.$ext;

        Storage::disk('documents')->put($storagePath, $contents);

        try {
            $version = DB::transaction(function () use ($document, $file, $uploader, $sha256, $storagePath) {
                $version = DocumentVersion::create([
                    'application_document_id' => $document->ulid,
                    'storage_path' => $storagePath,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size_bytes' => $file->getSize(),
                    'sha256_checksum' => $sha256,
                    'scan_status' => ScanStatus::Pending,
                    'uploaded_by' => $uploader->id,
                    'created_at' => now(),
                ]);

                $document->update([
                    'current_version_id' => $version->ulid,
                    'status' => DocumentStatus::Uploaded,
                ]);

                AuditLogger::log(
                    'document.uploaded',
                    $document,
                    [
                        'version_ulid' => $version->ulid,
                        'original_filename' => $file->getClientOriginalName(),
                    ],
                    $uploader->id,
                );

                return $version;
            });
        } catch (\Throwable $e) {
            Storage::disk('documents')->delete($storagePath);
            throw $e;
        }

        ScanDocumentVersionJob::dispatch($version->ulid)->onQueue('documents');

        return $version;
    }

    private function validateFile(UploadedFile $file, DocumentType $documentType): void
    {
        $mimeType = $file->getMimeType();

        if (! in_array($mimeType, $documentType->accepted_mime_types)) {
            throw ValidationException::withMessages([
                'file' => ['File type not accepted. Accepted: '.implode(', ', $documentType->accepted_mime_types)],
            ]);
        }

        $fileSizeKb = (int) ceil($file->getSize() / 1024);

        if ($fileSizeKb > $documentType->max_size_kb) {
            throw ValidationException::withMessages([
                'file' => ['File exceeds the maximum allowed size of '.$documentType->max_size_kb.'KB.'],
            ]);
        }
    }
}
