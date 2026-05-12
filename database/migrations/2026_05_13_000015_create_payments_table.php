<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('ulid')->primary();
            $table->ulid('visa_application_id');
            $table->foreign('visa_application_id')->references('ulid')->on('visa_applications')->restrictOnDelete();
            $table->string('status')->default('pending');
            // pending | processing | succeeded | failed | refunded | partially_refunded
            $table->string('provider')->default('stripe');
            $table->string('provider_payment_intent_id')->nullable()->index();
            $table->string('provider_checkout_session_id')->nullable()->index();
            $table->unsignedInteger('amount_subtotal'); // in cents
            $table->unsignedInteger('amount_total'); // in cents
            $table->char('currency', 3)->default('USD'); // ISO 4217
            $table->string('failure_reason')->nullable();
            $table->timestamp('succeeded_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
