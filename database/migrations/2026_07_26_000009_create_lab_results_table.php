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
            $table->string('result_value', 120)->nullable();
            $table->string('unit', 30)->nullable();
            $table->string('reference_range', 60)->nullable();
            $table->boolean('is_critical')->default(false);
            $table->boolean('is_abnormal')->default(false);
            $table->string('interpretation', 255)->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('critical_alert_acknowledged')->default(false);
            $table->string('client_uuid', 64)->unique()->nullable();
            $table->boolean('synced')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_results');
    }
};
