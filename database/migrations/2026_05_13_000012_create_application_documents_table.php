<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // current_version_id FK is added in migration 000014 after document_versions exists.
        Schema::create('application_documents', function (Blueprint $table) {
            $table->ulid('ulid')->primary();
            $table->ulid('visa_application_id');
            $table->foreign('visa_application_id')->references('ulid')->on('visa_applications')->cascadeOnDelete();
            $table->ulid('document_type_id');
            $table->foreign('document_type_id')->references('ulid')->on('document_types')->restrictOnDelete();
            $table->ulid('current_version_id')->nullable(); // FK added after document_versions is created
            $table->string('status')->default('pending');
            // pending | uploaded | pending_scan | under_review | accepted | rejected | infected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->unique(['visa_application_id', 'document_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_documents');
    }
};
