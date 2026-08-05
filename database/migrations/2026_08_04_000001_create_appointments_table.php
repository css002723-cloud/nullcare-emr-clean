<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('appointment_number', 24)->unique();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('department', 60)->default('General Clinic');
            $table->date('appointment_date');
            $table->time('appointment_time');
            $table->text('reason')->nullable();
            $table->string('priority', 20)->default('routine');
            $table->string('status', 20)->default('scheduled'); // scheduled, checked_in, completed, missed, cancelled
            $table->string('contact_phone', 30)->nullable();
            // Set once the patient actually arrives and is checked in — links
            // the appointment to the real visit it produced, so a booked slot
            // and the encounter it becomes are traceable to each other.
            $table->foreignId('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            $table->foreignId('booked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();

            $table->index(['appointment_date', 'status']);
            $table->index('doctor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
