<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete(); // who created the record offline
            $table->string('table_name', 100); // target table once synced
            $table->json('payload'); // the actual record data
            $table->enum('status', ['pending', 'synced', 'failed', 'conflict'])->default('pending');
            $table->timestamp('created_offline_at');
            $table->timestamp('synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_queue');
    }
};
