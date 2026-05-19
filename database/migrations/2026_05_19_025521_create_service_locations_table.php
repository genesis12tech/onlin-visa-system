<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_locations', function (Blueprint $table) {
            $table->ulid('ulid')->primary();
            $table->string('name');
            $table->text('address');
            $table->string('city');
            $table->foreignId('country_id')->constrained('countries');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_locations');
    }
};
