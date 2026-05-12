<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visa_types', function (Blueprint $table) {
            $table->ulid('ulid')->primary();
            $table->foreignId('country_id')->constrained('countries')->restrictOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('processing_days');
            $table->unsignedInteger('validity_days');
            $table->string('max_entries')->default('single'); // single | multiple | unlimited
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_types');
    }
};
