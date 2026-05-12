<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visa_fees', function (Blueprint $table) {
            $table->ulid('ulid')->primary();
            $table->ulid('visa_type_id');
            $table->foreign('visa_type_id')->references('ulid')->on('visa_types')->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('amount'); // in cents
            $table->char('currency', 3)->default('USD'); // ISO 4217
            $table->string('applicant_type')->default('all'); // all | adult | child | senior
            $table->date('effective_from');
            $table->date('effective_to')->nullable(); // null = still active
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_fees');
    }
};
