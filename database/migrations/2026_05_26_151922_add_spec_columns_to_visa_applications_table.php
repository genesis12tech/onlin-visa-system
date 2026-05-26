<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visa_applications', function (Blueprint $table) {
            $table->string('priority', 10)->default('normal')->after('status');
            $table->unsignedSmallInteger('days_pending')->nullable()->after('priority');
            $table->foreignId('decision_by')->nullable()->constrained('users')->nullOnDelete()->after('decision_reason');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('visa_applications', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropConstrainedForeignId('decision_by');
            $table->dropColumn(['priority', 'days_pending']);
        });
    }
};
