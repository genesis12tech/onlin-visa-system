<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_application_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->ulid('visa_type_id');
            $table->foreign('visa_type_id')->references('ulid')->on('visa_types')->cascadeOnDelete();
            $table->unsignedInteger('submitted_count')->default(0);
            $table->unsignedInteger('approved_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->unsignedInteger('pending_count')->default(0);
            $table->decimal('avg_processing_days', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['date', 'visa_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_application_metrics');
    }
};
