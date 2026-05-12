<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visa_type_document_requirements', function (Blueprint $table) {
            $table->ulid('ulid')->primary();
            $table->ulid('visa_type_id');
            $table->foreign('visa_type_id')->references('ulid')->on('visa_types')->cascadeOnDelete();
            $table->ulid('document_type_id');
            $table->foreign('document_type_id')->references('ulid')->on('document_types')->restrictOnDelete();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->text('notes')->nullable(); // shown to applicant
            $table->timestamps();

            $table->unique(['visa_type_id', 'document_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_type_document_requirements');
    }
};
