<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('prescribed_by')->constrained('users')->restrictOnDelete();
            $table->string('drug_name', 150);
            $table->string('formulation', 100)->nullable(); // tablet, syrup
            $table->string('dose', 50); // 500mg
            $table->string('route', 50)->nullable(); // oral, IV
            $table->string('frequency', 50)->nullable(); // twice daily
            $table->string('duration', 50)->nullable(); // 5 days
            $table->enum('status', ['pending', 'dispensed', 'partially_dispensed', 'cancelled'])->default('pending');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
