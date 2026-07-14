<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('clinician_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->enum('encounter_type', ['outpatient', 'inpatient', 'emergency']);
            $table->enum('triage_category', ['emergency', 'urgent', 'routine'])->nullable();
            $table->text('presenting_complaint')->nullable();
            $table->text('history')->nullable();
            $table->text('examination_findings')->nullable();
            $table->string('diagnosis', 255)->nullable();
            $table->string('diagnosis_code', 20)->nullable(); // ICD-10/11 code
            $table->text('clinical_plan')->nullable();
            $table->enum('status', ['open', 'closed', 'referred', 'admitted', 'discharged'])->default('open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounters');
    }
};
