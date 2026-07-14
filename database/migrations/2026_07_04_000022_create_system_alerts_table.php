<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['module_down', 'low_stock', 'sync_pending', 'user_approval', 'backup']);
            $table->string('message', 255);
            $table->enum('severity', ['info', 'warning', 'critical']);
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_alerts');
    }
};
