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
            $table->string('patient_uid', 20)->unique(); // permanent, anonymous, cross-visit
            $table->string('national_id', 40)->nullable();
            $table->string('given_name', 80);
            $table->string('family_name', 80);
            $table->string('sex', 10)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->unsignedSmallInteger('estimated_age')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('village', 80)->nullable();
            $table->string('traditional_authority', 80)->nullable();
            $table->string('district', 80)->nullable();
            $table->string('region', 40)->nullable();
            $table->string('occupation', 80)->nullable();
            $table->string('guardian_name', 120)->nullable();
            $table->string('guardian_relationship', 40)->nullable();
            $table->string('guardian_phone', 30)->nullable();
            $table->string('patient_category', 30)->nullable();
            $table->boolean('consent_care')->default(true);
            $table->boolean('consent_research')->default(false);
            $table->boolean('consent_teaching')->default(false);
            $table->boolean('is_deceased')->default(false);
            $table->timestamp('date_of_death')->nullable();
            $table->string('photo_url', 255)->nullable();
            $table->foreignId('merged_into_patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('client_uuid', 64)->unique()->nullable();
            $table->boolean('synced')->default(true);
            $table->timestamps();

            $table->index(['given_name', 'family_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
