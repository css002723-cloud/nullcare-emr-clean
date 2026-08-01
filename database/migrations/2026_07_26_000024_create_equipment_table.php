<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('equipment_type', 80)->nullable();
            $table->string('department', 60)->nullable();
            $table->string('serial_number', 80)->nullable();
            $table->string('status', 20)->default('operational');
            $table->date('install_date')->nullable();
            $table->timestamp('last_maintenance_at')->nullable();
            $table->date('next_maintenance_due')->nullable();
            $table->text('notes')->nullable();
            $table->string('client_uuid', 64)->unique()->nullable();
            $table->boolean('synced')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
