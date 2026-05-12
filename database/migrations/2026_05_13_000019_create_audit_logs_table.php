<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only. No updated_at.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('ulid')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_type'); // morph class name
            $table->string('subject_id'); // morph ID (string — subjects use ULIDs)
            $table->string('action'); // e.g. document.downloaded, application.approved
            $table->json('metadata'); // contextual data for the action
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index('action');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
