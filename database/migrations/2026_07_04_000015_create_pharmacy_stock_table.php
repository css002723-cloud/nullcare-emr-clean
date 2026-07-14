<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_stock', function (Blueprint $table) {
            $table->id();
            $table->string('drug_name', 150);
            $table->string('batch_number', 50)->nullable();
            $table->integer('quantity_available');
            $table->integer('reorder_threshold'); // triggers low-stock alert
            $table->date('expiry_date')->nullable();
            $table->boolean('is_controlled')->default(false);
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_stock');
    }
};
