<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_templates', function (Blueprint $table) {
            $table->ulid('ulid')->primary();
            $table->ulid('visa_type_id');
            $table->foreign('visa_type_id')->references('ulid')->on('visa_types')->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('version')->default(1);
            $table->json('schema'); // field definitions array
            $table->boolean('is_active')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_templates');
    }
};
