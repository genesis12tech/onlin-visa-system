<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_rejection_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->ulid('document_type_id');
            $table->foreign('document_type_id')->references('ulid')->on('document_types')->cascadeOnDelete();
            $table->unsignedInteger('rejection_count')->default(0);
            $table->json('top_reasons'); // array of {reason, count} objects
            $table->timestamps();

            $table->unique(['date', 'document_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_rejection_metrics');
    }
};
