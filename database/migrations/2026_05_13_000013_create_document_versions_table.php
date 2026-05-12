<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Immutable — written once per upload. No updated_at.
        Schema::create('document_versions', function (Blueprint $table) {
            $table->ulid('ulid')->primary();
            $table->ulid('application_document_id');
            $table->foreign('application_document_id')->references('ulid')->on('application_documents')->cascadeOnDelete();
            $table->string('storage_path'); // ULID-based private S3 key — never the original filename
            $table->string('original_filename'); // metadata only
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size_bytes');
            $table->char('sha256_checksum', 64); // computed on upload
            $table->string('scan_status')->default('pending'); // pending | clean | infected
            $table->timestamp('scan_completed_at')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('application_document_id');
            $table->index('scan_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');
    }
};
