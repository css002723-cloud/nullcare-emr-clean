<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('ordered_by')->constrained('users')->restrictOnDelete();
            $table->string('test_name', 150);
            $table->string('loinc_code', 20)->nullable(); // future standard mapping
            $table->string('specimen_type', 100)->nullable();
            $table->enum('status', ['ordered', 'collected', 'received', 'processing', 'completed', 'cancelled'])->default('ordered');
            $table->enum('urgency', ['routine', 'urgent', 'stat'])->default('routine');
            $table->timestamp('ordered_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_orders');
    }
};
