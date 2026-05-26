<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_application_metrics', function (Blueprint $table) {
            $table->dropUnique(['date', 'visa_type_id']);
            $table->dropForeign(['visa_type_id']);

            $table->string('visa_type_id', 26)->nullable()->change();

            $table->foreign('visa_type_id')->references('ulid')->on('visa_types')->nullOnDelete();

            $table->foreignId('officer_id')->nullable()->after('date')
                ->constrained('users')->nullOnDelete();

            $table->unique(['date', 'officer_id', 'visa_type_id']);
        });
    }

    public function down(): void
    {
        Schema::table('daily_application_metrics', function (Blueprint $table) {
            $table->dropUnique(['date', 'officer_id', 'visa_type_id']);
            $table->dropConstrainedForeignId('officer_id');
            $table->dropForeign(['visa_type_id']);
            $table->string('visa_type_id', 26)->nullable(false)->change();
            $table->foreign('visa_type_id')->references('ulid')->on('visa_types')->cascadeOnDelete();
            $table->unique(['date', 'visa_type_id']);
        });
    }
};
