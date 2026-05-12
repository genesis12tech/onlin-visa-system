<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicant_profiles', function (Blueprint $table) {
            $table->ulid('ulid')->primary();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->date('date_of_birth');
            $table->string('gender');
            $table->foreignId('nationality_id')->constrained('countries')->restrictOnDelete();
            $table->foreignId('country_of_residence_id')->constrained('countries')->restrictOnDelete();
            $table->text('passport_number'); // encrypted at rest
            $table->date('passport_expiry_date');
            $table->text('phone'); // encrypted at rest
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city');
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_profiles');
    }
};
