<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drug_stock', function (Blueprint $table) {
            $table->id();
            $table->string('drug_name', 120)->unique();
            $table->integer('quantity_on_hand')->default(0);
            $table->integer('reorder_level')->default(10);
            $table->string('unit', 20)->default('tablets');
            $table->boolean('is_controlled')->default(false);
            $table->date('expiry_date')->nullable();
            $table->string('client_uuid', 64)->unique()->nullable();
            $table->boolean('synced')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drug_stock');
    }
};
