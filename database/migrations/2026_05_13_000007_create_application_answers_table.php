<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_answers', function (Blueprint $table) {
            $table->ulid('ulid')->primary();
            $table->ulid('visa_application_id');
            $table->foreign('visa_application_id')->references('ulid')->on('visa_applications')->cascadeOnDelete();
            $table->string('field_key');
            $table->json('value'); // handles all field types (string, array, boolean, etc.)
            $table->timestamps();

            $table->unique(['visa_application_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_answers');
    }
};
