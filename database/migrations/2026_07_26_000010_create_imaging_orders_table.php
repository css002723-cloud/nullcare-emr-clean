<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imaging_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('modality', 10)->nullable();
            $table->string('study_description', 120)->nullable();
            $table->string('body_site', 60)->nullable();
            $table->text('clinical_indication')->nullable();
            $table->string('accession_number', 40)->nullable();
            $table->string('study_instance_uid', 80)->nullable();
            $table->boolean('is_pregnancy_checked')->default(false);
            $table->text('safety_checklist_notes')->nullable();
            $table->string('status', 30)->default('requested');
            $table->foreignId('ordered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 20)->default('routine');
            $table->string('client_uuid', 64)->unique()->nullable();
            $table->boolean('synced')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imaging_orders');
    }
};
