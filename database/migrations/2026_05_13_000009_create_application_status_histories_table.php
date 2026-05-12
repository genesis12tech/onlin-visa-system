<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only audit trail. No updated_at.
        Schema::create('application_status_histories', function (Blueprint $table) {
            $table->ulid('ulid')->primary();
            $table->ulid('visa_application_id');
            $table->foreign('visa_application_id')->references('ulid')->on('visa_applications')->cascadeOnDelete();
            $table->string('from_status')->nullable(); // null for initial creation
            $table->string('to_status');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('visa_application_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_status_histories');
    }
};
