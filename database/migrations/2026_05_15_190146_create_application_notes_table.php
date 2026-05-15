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
        Schema::create('application_notes', function (Blueprint $table) {
            $table->ulid('ulid')->primary();
            $table->char('visa_application_id', 26)->index();
            $table->foreign('visa_application_id')->references('ulid')->on('visa_applications')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users');
            $table->text('body');
            $table->boolean('is_visible_to_applicant')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_notes');
    }
};
