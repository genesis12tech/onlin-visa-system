<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visa_applications', function (Blueprint $table) {
            $table->ulid('ulid')->primary();
            $table->string('tracking_number')->unique();
            $table->ulid('applicant_profile_id');
            $table->foreign('applicant_profile_id')->references('ulid')->on('applicant_profiles')->restrictOnDelete();
            $table->ulid('visa_type_id');
            $table->foreign('visa_type_id')->references('ulid')->on('visa_types')->restrictOnDelete();
            $table->ulid('form_template_id');
            $table->foreign('form_template_id')->references('ulid')->on('form_templates')->restrictOnDelete();
            $table->string('status')->default('draft');
            // draft | submitted | payment_pending | payment_completed | under_review
            // | additional_info_requested | approved | rejected | withdrawn
            $table->foreignId('assigned_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decision_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('assigned_officer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_applications');
    }
};
