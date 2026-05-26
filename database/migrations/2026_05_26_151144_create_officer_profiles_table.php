<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('officer_profiles', function (Blueprint $table) {
            $table->ulid('ulid')->primary();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('display_initials', 4);
            $table->unsignedSmallInteger('capacity')->default(12);
            $table->json('specialisations')->nullable();
            $table->string('avatar_color', 30)->nullable();
            $table->boolean('is_accepting_assignments')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('officer_profiles');
    }
};
