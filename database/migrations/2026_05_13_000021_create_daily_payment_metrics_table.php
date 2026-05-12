<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_payment_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->char('currency', 3); // ISO 4217
            $table->unsignedInteger('total_collected')->default(0); // cents
            $table->unsignedInteger('total_refunded')->default(0); // cents
            $table->unsignedInteger('succeeded_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamps();

            $table->unique(['date', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_payment_metrics');
    }
};
