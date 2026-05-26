<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_application_metrics', function (Blueprint $table) {
            // Drop old unique index before altering columns
            $table->dropUnique(['date', 'visa_type_id']);

            // Make visa_type_id nullable
            $table->string('visa_type_id', 26)->nullable()->change();

            // Add officer_id FK after date
            $table->foreignId('officer_id')->nullable()->after('date')
                ->constrained('users')->nullOnDelete();

            // New composite unique index
            $table->unique(['date', 'officer_id', 'visa_type_id']);
        });
    }

    public function down(): void
    {
        Schema::table('daily_application_metrics', function (Blueprint $table) {
            $table->dropUnique(['date', 'officer_id', 'visa_type_id']);
            $table->dropConstrainedForeignId('officer_id');
            $table->string('visa_type_id', 26)->nullable(false)->change();
            $table->unique(['date', 'visa_type_id']);
        });
    }
};
