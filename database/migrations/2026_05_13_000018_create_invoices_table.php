<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->ulid('ulid')->primary();
            $table->ulid('payment_id');
            $table->foreign('payment_id')->references('ulid')->on('payments')->restrictOnDelete();
            $table->string('invoice_number')->unique();
            $table->timestamp('issued_at');
            $table->string('pdf_storage_path')->nullable(); // private S3 key; set after PDF job completes
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
