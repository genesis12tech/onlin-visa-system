<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_items', function (Blueprint $table) {
            $table->ulid('ulid')->primary();
            $table->ulid('payment_id');
            $table->foreign('payment_id')->references('ulid')->on('payments')->cascadeOnDelete();
            $table->ulid('visa_fee_id');
            $table->foreign('visa_fee_id')->references('ulid')->on('visa_fees')->restrictOnDelete();
            $table->string('description'); // snapshot of fee name at time of payment
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('unit_amount'); // in cents
            $table->unsignedInteger('total_amount'); // in cents
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_items');
    }
};
