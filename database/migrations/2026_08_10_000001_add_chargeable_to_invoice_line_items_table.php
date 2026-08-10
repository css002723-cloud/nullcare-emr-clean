<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links an invoice line item back to the clinical record it was
     * billed from (a lab order, imaging order, prescription, or dialysis
     * session). This is how "pending charges" pulls only unbilled items —
     * anything already linked here is excluded from the next pull.
     */
    public function up(): void
    {
        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->string('chargeable_type', 60)->nullable()->after('invoice_id');
            $table->unsignedBigInteger('chargeable_id')->nullable()->after('chargeable_type');
            $table->index(['chargeable_type', 'chargeable_id']);
        });
    }

    public function down(): void
    {
        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->dropIndex(['chargeable_type', 'chargeable_id']);
            $table->dropColumn(['chargeable_type', 'chargeable_id']);
        });
    }
};
