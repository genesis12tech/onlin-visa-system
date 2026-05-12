<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Immutable — written once at submission. No updated_at.
        Schema::create('application_snapshots', function (Blueprint $table) {
            $table->ulid('ulid')->primary();
            $table->ulid('visa_application_id');
            $table->foreign('visa_application_id')->references('ulid')->on('visa_applications')->cascadeOnDelete();
            $table->json('snapshot_data'); // full application state at submission
            $table->timestamp('created_at')->useCurrent();

            $table->unique('visa_application_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_snapshots');
    }
};
