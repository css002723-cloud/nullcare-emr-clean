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
            $table->string('encounter_number', 24)->unique();
            $table->string('mrn', 20)->unique(); // per-visit hospital number, NOT permanent
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('visit_type', 20)->default('outpatient');
            $table->string('stage', 30)->default('registered');
            $table->string('priority', 20)->default('routine');
            $table->boolean('is_emergency')->default(false);
            $table->string('chief_complaint', 255)->nullable();
            $table->string('current_department', 60)->default('reception');
            $table->foreignId('assigned_clinician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ward', 60)->nullable();
            $table->string('bed', 20)->nullable();
            $table->text('admission_diagnosis')->nullable();
            $table->string('outcome', 30)->nullable();
            $table->text('disposition_notes')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('client_uuid', 64)->unique()->nullable();
            $table->boolean('synced')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounters');
    }
};
