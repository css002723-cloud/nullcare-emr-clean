<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dialysis_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            $table->string('ckd_stage', 10)->nullable();
            $table->timestamp('session_date')->nullable();
            $table->float('pre_weight_kg')->nullable();
            $table->float('post_weight_kg')->nullable();
            $table->float('fluid_removal_target_l')->nullable();
            $table->string('vascular_access_type', 40)->nullable();
            $table->text('complications')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('client_uuid', 64)->unique()->nullable();
            $table->boolean('synced')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dialysis_sessions');
    }
};
