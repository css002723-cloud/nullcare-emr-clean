<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('note_type', 40)->nullable();
            $table->string('clinic_template', 30)->nullable();
            $table->text('presenting_complaint')->nullable();
            $table->text('history_of_presenting_illness')->nullable();
            $table->text('past_medical_history')->nullable();
            $table->text('past_surgical_history')->nullable();
            $table->text('medication_history')->nullable();
            $table->text('allergy_history')->nullable();
            $table->text('social_history')->nullable();
            $table->text('family_history')->nullable();
            $table->text('review_of_systems')->nullable();
            $table->text('examination_findings')->nullable();
            $table->text('diagnosis')->nullable();
            $table->string('icd_code', 20)->nullable();
            $table->text('differential_diagnosis')->nullable();
            $table->text('plan')->nullable();
            $table->text('follow_up_plan')->nullable();
            $table->text('body')->nullable();
            $table->text('history')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_role', 30)->nullable();
            $table->boolean('signed')->default(true);
            $table->string('signed_by_name', 120)->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('client_uuid', 64)->unique()->nullable();
            $table->boolean('synced')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_notes');
    }
};
