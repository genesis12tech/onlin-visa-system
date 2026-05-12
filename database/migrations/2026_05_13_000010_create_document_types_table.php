<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->ulid('ulid')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('accepted_mime_types'); // array of allowed MIME strings
            $table->unsignedInteger('max_size_kb');
            $table->unsignedInteger('max_pages')->nullable(); // for multi-page documents
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};
