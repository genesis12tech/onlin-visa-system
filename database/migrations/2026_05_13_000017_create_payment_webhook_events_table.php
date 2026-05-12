<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotency table — unique event_id prevents duplicate processing.
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->ulid('ulid')->primary();
            $table->string('provider')->default('stripe');
            $table->string('event_id')->unique(); // provider event ID — unique constraint prevents duplicates
            $table->string('event_type'); // e.g. payment_intent.succeeded
            $table->json('payload'); // raw webhook body
            $table->timestamp('processed_at')->nullable(); // null = not yet processed
            $table->text('processing_error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('event_type');
            $table->index('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
    }
};
