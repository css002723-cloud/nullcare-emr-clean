<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icu_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('note_type', 30)->default('daily_review');
            $table->string('ventilation_status', 30)->nullable();
            $table->string('oxygen_therapy', 120)->nullable();
            $table->string('sedation_assessment', 120)->nullable();
            $table->string('inotropes', 200)->nullable();
            $table->string('fluid_balance_summary', 200)->nullable();
            $table->boolean('sepsis_alert')->default(false);
            $table->text('body')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_role', 30)->nullable();
            $table->string('client_uuid', 64)->unique()->nullable();
            $table->boolean('synced')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icu_notes');
    }
};
