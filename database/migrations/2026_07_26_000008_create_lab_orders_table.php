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
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('test_code', 30)->nullable(); // key into LOINC_TEST_CATALOG (code constant, not a DB table)
            $table->string('loinc_code', 20)->nullable();
            $table->string('loinc_display', 120)->nullable();
            $table->string('specimen_type', 60)->nullable();
            $table->string('barcode', 40)->nullable();
            $table->string('status', 30)->default('ordered');
            $table->string('priority', 20)->default('routine');
            $table->foreignId('ordered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('client_uuid', 64)->unique()->nullable();
            $table->boolean('synced')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_orders');
    }
};
