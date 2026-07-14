<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->string('ward_name', 100);
            $table->string('bed_number', 20)->nullable();
            $table->string('admission_diagnosis', 255)->nullable();
            $table->foreignId('admitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('admitted_at')->useCurrent();
            $table->timestamp('discharged_at')->nullable();
            $table->text('discharge_summary')->nullable();
            $table->enum('outcome', ['discharged', 'transferred', 'died'])->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admissions');
    }
};
