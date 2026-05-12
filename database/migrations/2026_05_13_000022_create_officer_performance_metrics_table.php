<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('officer_performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('officer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('reviewed_count')->default(0);
            $table->unsignedInteger('approved_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->unsignedInteger('info_requested_count')->default(0);
            $table->decimal('avg_review_hours', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['date', 'officer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('officer_performance_metrics');
    }
};
