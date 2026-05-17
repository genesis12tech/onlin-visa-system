<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_status_histories', function (Blueprint $table): void {
            $table->string('public_label')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('application_status_histories', function (Blueprint $table): void {
            $table->dropColumn('public_label');
        });
    }
};
