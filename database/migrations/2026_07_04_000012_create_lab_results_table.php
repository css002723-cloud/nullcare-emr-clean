<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_id')->constrained('lab_orders')->cascadeOnDelete();
            $table->string('result_value', 255);
            $table->string('unit', 50)->nullable();
            $table->string('reference_range', 100)->nullable();
            $table->boolean('is_critical')->default(false); // triggers alert
            $table->foreignId('entered_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('result_date')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_results');
    }
};
