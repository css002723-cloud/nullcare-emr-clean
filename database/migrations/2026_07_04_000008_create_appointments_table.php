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
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('users')->nullOnDelete(); // assigned clinician
            $table->dateTime('appointment_date');
            $table->enum('status', ['scheduled', 'checked_in', 'completed', 'missed', 'cancelled'])->default('scheduled');
            $table->enum('visit_type', ['walk_in', 'scheduled', 'follow_up', 'emergency']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
