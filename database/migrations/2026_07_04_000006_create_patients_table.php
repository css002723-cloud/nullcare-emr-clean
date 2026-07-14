<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('patient_number', 20)->unique(); // system-generated hospital number
            $table->string('national_id', 30)->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->enum('gender', ['male', 'female', 'other']);
            $table->date('date_of_birth')->nullable();
            $table->integer('age_estimate')->nullable(); // for cases without confirmed DOB
            $table->string('phone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('village', 100)->nullable();
            $table->string('traditional_authority', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('occupation', 100)->nullable();
            $table->enum('patient_category', ['outpatient', 'inpatient', 'student', 'staff', 'private', 'emergency', 'research']);
            $table->string('guardian_name', 150)->nullable();
            $table->string('guardian_phone', 20)->nullable();
            $table->string('guardian_relationship', 50)->nullable();
            $table->boolean('consent_care')->default(true);
            $table->boolean('consent_teaching')->default(false);
            $table->boolean('consent_research')->default(false);
            $table->foreignId('is_duplicate_of')->nullable()->constrained('patients')->nullOnDelete(); // merge tracking
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['first_name', 'last_name']);
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
