<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('application_exports', function (Blueprint $table) {
            $table->ulid('ulid')->primary();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->json('filters')->nullable();
            $table->string('status')->default('queued'); // queued | processing | ready | failed
            $table->string('file_path')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index('requested_by');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_exports');
    }
};
