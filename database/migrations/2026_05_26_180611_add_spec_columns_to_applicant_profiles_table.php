<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->timestamp('profile_completed_at')->nullable()->after('postal_code');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('profile_completed_at');
        });
    }
};
