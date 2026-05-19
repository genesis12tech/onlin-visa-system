<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_appointments', function (Blueprint $table) {
            $table->string('status')->default('scheduled')->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('application_appointments', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
