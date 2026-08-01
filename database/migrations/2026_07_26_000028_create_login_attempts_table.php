<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every login attempt, success or failure — the raw log that backs
     * brute-force alerting. Deliberately a flat, simple table: "many
     * failed attempts" is computed on read (grouped by username within a
     * time window) rather than needing a separate stateful lockout
     * record kept in sync — matches the Python reference exactly.
     */
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('username', 80)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->boolean('success')->default(false);
            $table->timestamp('timestamp')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
